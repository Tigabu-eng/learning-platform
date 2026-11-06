<?php

namespace Tests\Feature;

use App\Mail\EnrolledByStaff;
use App\Mail\OrderReceipt;
use App\Mail\Welcome;
use App\Models\Course;
use App\Models\User;
use App\Services\Checkout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function course(array $attrs = []): Course
    {
        $i = User::factory()->create(['role' => 'instructor']);
        $c = Course::create(array_merge(['instructor_id' => $i->id, 'title' => 'Test course', 'status' => 'published', 'price' => 50], $attrs));
        $ch = $c->chapters()->create(['title' => 'One', 'position' => 1]);
        $ch->lessons()->create(['course_id' => $c->id, 'title' => 'A', 'type' => 'text', 'position' => 1]);
        return $c;
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))->assertSee('Forgot password?')->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
    }

    public function test_forgot_password_emails_a_link_and_gives_the_same_answer_for_unknown_emails(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'ann@example.com']);

        $this->post(route('password.email'), ['email' => 'ann@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($u, ResetPassword::class);

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'If an account uses that email'));
        Notification::assertCount(1);
    }

    public function test_reset_link_sets_a_new_password_and_logs_in(): void
    {
        $u = User::factory()->create(['email' => 'ann@example.com']);
        $token = Password::broker('users')->createToken($u);

        $this->get(route('password.reset', ['token' => $token, 'email' => $u->email]))->assertOk()->assertSee('Choose a new password');
        $this->post(route('password.update'), ['token' => $token, 'email' => $u->email, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(Hash::check('new-secret-1', $u->fresh()->password));

        // The link works once.
        auth()->logout();
        $this->post(route('password.update'), ['token' => $token, 'email' => $u->email, 'password' => 'other-secret-2', 'password_confirmation' => 'other-secret-2'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-secret-1', $u->fresh()->password));
    }

    public function test_expired_reset_link_is_refused(): void
    {
        $u = User::factory()->create();
        $token = Password::broker('users')->createToken($u);
        $this->travel(61)->minutes();
        $this->post(route('password.update'), ['token' => $token, 'email' => $u->email, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sign_up_sends_a_welcome_email(): void
    {
        Mail::fake();
        $this->post(route('register'), ['name' => 'Ann Lee', 'email' => 'ann@example.com', 'password' => 'secret-123', 'password_confirmation' => 'secret-123'])
            ->assertRedirect(route('dashboard'));
        Mail::assertSent(Welcome::class, fn ($m) => $m->hasTo('ann@example.com'));
    }

    public function test_sign_up_still_works_when_the_mail_server_fails(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        $this->post(route('register'), ['name' => 'Ann Lee', 'email' => 'ann@example.com', 'password' => 'secret-123', 'password_confirmation' => 'secret-123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_a_paid_order_sends_one_receipt(): void
    {
        Mail::fake();
        $c = $this->course();
        $s = User::factory()->create();
        $checkout = app(Checkout::class);
        $order = $checkout->createOrder($s, $c, null, 'test');

        $checkout->fulfil($order);
        $checkout->fulfil($order); // a webhook and the return page can both report it
        Mail::assertSent(OrderReceipt::class, 1);
        Mail::assertSent(OrderReceipt::class, fn ($m) => $m->hasTo($s->email));

        $html = (new OrderReceipt($order->fresh()))->render();
        $this->assertStringContainsString($order->reference, $html);
        $this->assertStringContainsString('Total paid', $html);
    }

    public function test_staff_enrolling_a_new_person_emails_a_set_password_link(): void
    {
        Mail::fake();
        $c = $this->course();
        $this->actingAs($c->instructor)->post(route('studio.students.enroll', $c), ['email' => 'new@example.com', 'name' => 'New Person', 'notify' => 1])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'link to choose a password'));

        $sent = null;
        Mail::assertSent(EnrolledByStaff::class, function ($m) use (&$sent) { $sent = $m; return $m->hasTo('new@example.com'); });
        $this->assertNotNull($sent->setPasswordUrl);

        // The invite link still works after 3 days (a normal reset link would have expired).
        parse_str(parse_url($sent->setPasswordUrl, PHP_URL_QUERY), $q);
        $token = basename(parse_url($sent->setPasswordUrl, PHP_URL_PATH));
        $this->assertSame('1', $q['invite']);
        auth()->logout();
        $this->travel(3)->days();
        $this->get($sent->setPasswordUrl)->assertOk()->assertSee('Choose your password');
        $this->post(route('password.update'), ['token' => $token, 'email' => 'new@example.com', 'invite' => 1, 'password' => 'chosen-pass-1', 'password_confirmation' => 'chosen-pass-1'])
            ->assertRedirect(route('dashboard'));
        $this->assertTrue(Hash::check('chosen-pass-1', User::where('email', 'new@example.com')->first()->password));
    }

    public function test_staff_enrolling_an_existing_student_sends_the_course_link_or_nothing(): void
    {
        Mail::fake();
        $c = $this->course();
        $s = User::factory()->create();
        $this->actingAs($c->instructor)->post(route('studio.students.enroll', $c), ['email' => $s->email, 'notify' => 1]);
        Mail::assertSent(EnrolledByStaff::class, fn ($m) => $m->hasTo($s->email) && $m->setPasswordUrl === null);

        $other = User::factory()->create();
        $this->post(route('studio.students.enroll', $c), ['email' => $other->email])
            ->assertSessionHas('status', fn ($st) => str_contains($st, 'No email was sent'));
        Mail::assertSent(EnrolledByStaff::class, 1);
    }
}
