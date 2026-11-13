<?php

namespace Tests\Feature;

use App\Mail\OrderReceipt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class TpdIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $ssoSecret = 'test-tpd-sso-secret-key-at-least-32-chars';
    private string $apiToken = 'test-tpd-api-bearer-token-at-least-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'tpd.sso_secret' => $this->ssoSecret,
            'tpd.api_token' => $this->apiToken,
            'tpd.sso_ttl' => 120,
            'tpd.frame_ancestors' => "'self' https://tpd.tarctpc.com",
            'tpd.webhook_url' => null,
        ]);
    }

    private function signSsoPayload(array $overrides = []): array
    {
        $now = time();
        $payload = array_merge([
            'tpd_user_id' => 101,
            'email' => 'advisor@example.com',
            'name' => 'Amina Advisor',
            'role' => 'travel_advisor',
            'iat' => $now,
            'exp' => $now + 120,
            'nonce' => Str::random(24),
        ], $overrides);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $rawToken = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $rawToken, $this->ssoSecret);

        return ['token' => $rawToken, 'sig' => $sig];
    }

    private function makeCourse(array $courseAttrs = [], bool $withLesson = true): Course
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'name' => 'Lead Instructor']);
        $course = Course::create(array_merge([
            'instructor_id' => $instructor->id,
            'title' => 'RTA Specialist Prep',
            'subtitle' => 'Foundational booking mastery',
            'price' => 79.00,
            'status' => 'published',
        ], $courseAttrs));

        if ($withLesson) {
            $chapter = $course->chapters()->create(['title' => 'Module 1', 'position' => 1]);
            $chapter->lessons()->create([
                'course_id' => $course->id,
                'title' => 'Welcome Lesson',
                'type' => 'text',
                'duration_minutes' => 15,
                'position' => 1,
            ]);
        }

        return $course;
    }

    public function test_sso_logs_in_and_provisions_advisor_as_student(): void
    {
        $query = $this->signSsoPayload([
            'tpd_user_id' => 55,
            'email' => 'pro.advisor@example.com',
            'name' => 'Sara Pro',
            'role' => 'travel_advisor',
            'redirect' => '/dashboard',
        ]);

        $response = $this->get(route('tpd.sso', $query));

        $response->assertRedirect('/dashboard');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://tpd.tarctpc.com");

        $user = User::where('email', 'pro.advisor@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(55, (int) $user->tpd_user_id);
        $this->assertSame('student', $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_sso_maps_wordpress_administrator_to_super_admin_and_allows_studio_iframe(): void
    {
        $query = $this->signSsoPayload([
            'tpd_user_id' => 1,
            'email' => 'super@tarctpc.com',
            'name' => 'TARC Owner',
            'role' => 'administrator',
            'redirect' => '/studio',
        ]);

        $this->get(route('tpd.sso', $query))->assertRedirect('/studio');

        $admin = User::where('email', 'super@tarctpc.com')->first();
        $this->assertSame('super_admin', $admin->role);

        $studioResponse = $this->actingAs($admin)->get(route('studio.dashboard'));
        $studioResponse->assertOk();
        $studioResponse->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://tpd.tarctpc.com");
        $this->assertFalse($studioResponse->headers->has('X-Frame-Options'));

        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResponse->assertOk();
        $adminResponse->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://tpd.tarctpc.com");
    }

    public function test_sso_rejects_bad_signature_expired_token_replay_and_open_redirect(): void
    {
        // Bad signature
        $badSig = $this->signSsoPayload();
        $badSig['sig'] = str_repeat('0', 64);
        $this->get(route('tpd.sso', $badSig))->assertForbidden();

        // Expired token
        $expired = $this->signSsoPayload(['iat' => time() - 300, 'exp' => time() - 10]);
        $this->get(route('tpd.sso', $expired))->assertForbidden();

        // Single-use nonce replay
        $once = $this->signSsoPayload(['nonce' => 'unique-nonce-1234567890']);
        $this->get(route('tpd.sso', $once))->assertRedirect('/dashboard');
        $this->post(route('logout'));
        $this->get(route('tpd.sso', $once))->assertForbidden();

        // Open redirect neutralized safely to local home
        $evilRedirect = $this->signSsoPayload([
            'tpd_user_id' => 77,
            'email' => 'safe@example.com',
            'redirect' => 'https://evil.example/phish',
        ]);
        $this->get(route('tpd.sso', $evilRedirect))->assertRedirect('/dashboard');
    }

    public function test_sso_does_not_downgrade_existing_super_admin(): void
    {
        $super = User::factory()->create([
            'email' => 'owner@tarctpc.com',
            'role' => 'super_admin',
        ]);

        $query = $this->signSsoPayload([
            'tpd_user_id' => 9,
            'email' => 'owner@tarctpc.com',
            'name' => 'Owner',
            'role' => 'travel_advisor',
        ]);

        $this->get(route('tpd.sso', $query))->assertRedirect('/admin');
        $this->assertSame('super_admin', $super->fresh()->role);
        $this->assertSame(9, (int) $super->fresh()->tpd_user_id);
    }

    public function test_tpd_api_requires_valid_bearer_token(): void
    {
        $this->getJson('/api/tpd/health')->assertUnauthorized();
        $this->withToken('wrong-token')->getJson('/api/tpd/health')->assertUnauthorized();

        $this->withToken($this->apiToken)
            ->getJson('/api/tpd/health')
            ->assertOk()
            ->assertJson(['ok' => true, 'version' => 'tpd-bridge-1']);

        config(['tpd.api_token' => '']);
        $this->withToken($this->apiToken)->getJson('/api/tpd/health')->assertStatus(503);
    }

    public function test_catalog_endpoint_lists_published_courses_and_supports_all_filter(): void
    {
        $published = $this->makeCourse(['title' => 'Luxury Cruise Mastery', 'status' => 'published']);
        $draft = $this->makeCourse(['title' => 'Internal Draft Course', 'status' => 'draft']);

        $this->withToken($this->apiToken)
            ->getJson('/api/tpd/catalog')
            ->assertOk()
            ->assertJsonCount(1, 'courses')
            ->assertJsonPath('courses.0.slug', $published->slug)
            ->assertJsonPath('courses.0.lessons_count', 1);

        $this->withToken($this->apiToken)
            ->getJson('/api/tpd/catalog?status=all')
            ->assertOk()
            ->assertJsonCount(2, 'courses')
            ->assertJsonFragment(['slug' => $draft->slug, 'status' => 'draft']);
    }

    public function test_enroll_endpoint_creates_paid_order_and_enrollment_idempotently(): void
    {
        Mail::fake();
        $course = $this->makeCourse(['price' => 99.00]);

        $payload = [
            'tpd_user_id' => 204,
            'email' => 'buyer@example.com',
            'name' => 'Buyer Advisor',
            'course' => $course->slug,
            'external_reference' => 'TPD-STRIPE-CS-001',
            'amount' => 99.00,
            'discount' => 10.00,
            'total' => 89.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'meta' => ['tpd_plan' => 'pro'],
        ];

        $first = $this->withToken($this->apiToken)->postJson('/api/tpd/enroll', $payload);
        $first->assertCreated()
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('order.reference', 'TPD-STRIPE-CS-001')
            ->assertJsonPath('order.status', 'paid')
            ->assertJsonPath('order.total', 89)
            ->assertJsonPath('enrollment.course_slug', $course->slug);

        // Second call with the same external_reference must be cleanly idempotent.
        $second = $this->withToken($this->apiToken)->postJson('/api/tpd/enroll', $payload);
        $second->assertOk()
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('order.reference', 'TPD-STRIPE-CS-001');

        $this->assertSame(1, Order::where('reference', 'TPD-STRIPE-CS-001')->count());
        $this->assertSame(1, Enrollment::count());
        Mail::assertSent(OrderReceipt::class, 1);

        // Reusing the same reference for a different course fails with 409 Conflict.
        $other = $this->makeCourse(['title' => 'Another Course']);
        $this->withToken($this->apiToken)
            ->postJson('/api/tpd/enroll', array_merge($payload, ['course' => $other->slug]))
            ->assertStatus(409);
    }

    public function test_enrollments_endpoint_returns_progress_and_certificate_or_empty_for_unknown_user(): void
    {
        $this->withToken($this->apiToken)
            ->getJson('/api/tpd/enrollments?tpd_user_id=99999&email=missing@example.com')
            ->assertOk()
            ->assertJson(['user' => null, 'enrollments' => []]);

        $course = $this->makeCourse(['certificate_enabled' => true]);
        $student = User::factory()->create(['tpd_user_id' => 305, 'email' => 'grad@example.com']);
        $progress = app(Progress::class);
        $enrollment = $progress->enroll($student, $course);
        $progress->complete($enrollment, $course->lessons()->first());

        $this->withToken($this->apiToken)
            ->getJson('/api/tpd/enrollments?tpd_user_id=305')
            ->assertOk()
            ->assertJsonPath('user.tpd_user_id', 305)
            ->assertJsonPath('enrollments.0.course_slug', $course->slug)
            ->assertJsonPath('enrollments.0.progress_percent', 100)
            ->assertJsonPath('enrollments.0.certificate.code', $enrollment->fresh()->certificate->code);
    }

    public function test_admin_overview_and_publish_toggle_endpoints(): void
    {
        $readyDraft = $this->makeCourse(['title' => 'Ready Draft', 'status' => 'draft'], true);
        $emptyDraft = $this->makeCourse(['title' => 'Empty Draft', 'status' => 'draft'], false);

        $this->withToken($this->apiToken)
            ->postJson('/api/tpd/courses/'.$emptyDraft->slug.'/publish')
            ->assertStatus(422);

        $this->withToken($this->apiToken)
            ->postJson('/api/tpd/courses/'.$readyDraft->slug.'/publish')
            ->assertOk()
            ->assertJson(['slug' => $readyDraft->slug, 'status' => 'published']);

        $this->withToken($this->apiToken)
            ->getJson('/api/tpd/admin/overview')
            ->assertOk()
            ->assertJsonPath('stats.courses', 2)
            ->assertJsonPath('stats.published_courses', 1)
            ->assertJsonPath('paths.studio', '/studio');
    }

    public function test_outbound_webhook_is_sent_with_hmac_signature_when_configured(): void
    {
        Http::fake(['https://tpd.tarctpc.com/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        config([
            'tpd.webhook_url' => 'https://tpd.tarctpc.com/wp-json/tpd/v1/lms-webhook',
            'tpd.webhook_secret' => 'webhook-signing-secret',
        ]);

        $course = $this->makeCourse(['price' => 45.00]);

        $this->withToken($this->apiToken)->postJson('/api/tpd/enroll', [
            'tpd_user_id' => 408,
            'email' => 'hookuser@example.com',
            'name' => 'Hook User',
            'course' => $course->slug,
            'external_reference' => 'TPD-HOOK-001',
        ])->assertCreated();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $expectedSig = hash_hmac('sha256', $body, 'webhook-signing-secret');

            return $request->url() === 'https://tpd.tarctpc.com/wp-json/tpd/v1/lms-webhook'
                && $request->header('X-LMS-Event')[0] === 'order.paid'
                && hash_equals($expectedSig, $request->header('X-LMS-Signature')[0] ?? '');
        });
    }
}
