<?php

namespace Tests\Feature;

use App\Mail\NewProctorRequest;
use App\Mail\ProctorAssigned;
use App\Mail\ProctorRequestDecided;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAccessCode;
use App\Models\Lesson;
use App\Models\ProctorRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProctoringTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Lesson $exam;
    private Quiz $quiz;
    private User $student;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        config(['school.brand.support_email' => 'support@tarc.example']);

        $instructor = User::factory()->create(['role' => 'instructor', 'email' => 'steven@tarc.example']);
        $this->course = Course::create(['instructor_id' => $instructor->id, 'title' => 'RTA', 'status' => 'published']);
        $chapter = $this->course->chapters()->create(['title' => 'Final', 'position' => 1]);
        $this->exam = $chapter->lessons()->create(['course_id' => $this->course->id, 'title' => 'RTA final exam', 'type' => 'exam', 'position' => 1]);
        $this->quiz = Quiz::create(['lesson_id' => $this->exam->id, 'is_final' => true, 'access_mode' => 'code', 'max_attempts' => 3, 'time_limit_minutes' => 20]);
        $q = $this->quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q', 'points' => 1]);
        $q->options()->createMany([['text' => 'True', 'is_correct' => true, 'position' => 1], ['text' => 'False', 'is_correct' => false, 'position' => 2]]);

        $this->student = User::factory()->create(['email' => 'daniel@example.com', 'name' => 'Daniel Reyes']);
        $this->enrollment = app(Progress::class)->enroll($this->student, $this->course);
    }

    private function requestData(array $overrides = []): array
    {
        return array_merge([
            'proctor_name' => 'Pat Proctor', 'proctor_email' => 'Pat@Example.com', 'proctor_phone' => '+1 555 0100',
            'proctor_relationship' => 'Manager at my host agency', 'preferred_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'timezone' => 'America/New_York', 'student_notes' => 'Zoom', 'agree' => '1',
        ], $overrides);
    }

    private function submitRequest(array $overrides = [])
    {
        return $this->actingAs($this->student)->from(route('proctor-requests.create', [$this->course, $this->exam]))
            ->post(route('proctor-requests.store', [$this->course, $this->exam]), $this->requestData($overrides));
    }

    private function approve(?User $staff = null, array $data = ['valid_hours' => 72, 'note' => 'Zoom link: https://zoom.example/123']): string
    {
        $token = null;
        $this->actingAs($staff ?? $this->course->instructor)->from(route('studio.proctoring'))
            ->post(route('studio.proctoring.approve', ProctorRequest::first()), $data)->assertSessionHasNoErrors();
        Mail::assertSent(ProctorAssigned::class, function ($m) use (&$token) { $token = $m->token; return true; });
        return $token;
    }

    public function test_student_requests_a_proctor_and_staff_are_notified(): void
    {
        $this->actingAs($this->student)->get(route('learn.lesson', [$this->course, $this->exam]))->assertSee('Request a proctored exam');
        $this->get(route('proctor-requests.create', [$this->course, $this->exam]))->assertOk()->assertSee('Your proctor');

        $this->submitRequest()->assertRedirect(route('proctor-requests.create', [$this->course, $this->exam]));
        $r = ProctorRequest::first();
        $this->assertSame(['pending', 'pat@example.com', 'America/New_York'], [$r->status, $r->proctor_email, $r->timezone]);
        $this->assertSame('UTC', $r->preferred_at->timezoneName);
        Mail::assertSent(NewProctorRequest::class, fn ($m) => $m->hasTo('support@tarc.example') && $m->hasTo('steven@tarc.example'));

        $this->get(route('proctor-requests.create', [$this->course, $this->exam]))->assertSee('waiting for review')->assertDontSee('Send request');
        $this->get(route('learn.lesson', [$this->course, $this->exam]))->assertSee('waiting for review');
        // Only one open request at a time.
        $this->submitRequest()->assertStatus(409);
        $this->assertSame(1, ProctorRequest::count());
    }

    public function test_request_validation(): void
    {
        $this->submitRequest(['proctor_email' => 'daniel@example.com'])->assertSessionHasErrors(['proctor_email' => 'Your proctor must be someone else, with their own email address.']);
        $this->submitRequest(['agree' => null])->assertSessionHasErrors('agree');
        $this->submitRequest(['preferred_date' => now()->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('preferred_date');
        $this->submitRequest(['timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');
        $this->assertSame(0, ProctorRequest::count());

        // Exams without one-time codes have no request page.
        $this->quiz->update(['access_mode' => 'open']);
        $this->get(route('proctor-requests.create', [$this->course, $this->exam]))->assertNotFound();
    }

    public function test_student_can_cancel_a_pending_request_and_send_another(): void
    {
        $this->submitRequest();
        $r = ProctorRequest::first();
        $this->post(route('proctor-requests.cancel', [$this->course, $this->exam, $r]))->assertRedirect();
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->submitRequest()->assertSessionHasNoErrors();
        $this->assertSame(2, ProctorRequest::count());

        // Someone else's request can't be cancelled.
        $other = User::factory()->create();
        app(Progress::class)->enroll($other, $this->course);
        $this->actingAs($other)->post(route('proctor-requests.cancel', [$this->course, $this->exam, ProctorRequest::latest('id')->first()]))->assertNotFound();
    }

    public function test_approval_creates_the_code_and_emails_proctor_and_student(): void
    {
        $this->submitRequest();
        $this->actingAs($this->course->instructor)->get(route('studio.proctoring'))->assertOk()->assertSee('Pat Proctor')->assertSee('Approve and email the proctor');
        $token = $this->approve();

        $r = ProctorRequest::first();
        $code = $r->code;
        $this->assertSame(['approved', 'pat@example.com', 'Pat Proctor'], [$r->status, $code->proctor_email, $code->proctor_name]);
        // Valid 3 days, or a day past the requested time, whichever is later.
        $this->assertTrue($code->expires_at->gte($r->preferred_at->copy()->addDay()->subMinute()));

        Mail::assertSent(ProctorAssigned::class, fn ($m) => $m->hasTo('pat@example.com') && str_contains($m->render(), $code->code) && str_contains($m->render(), 'zoom.example'));
        Mail::assertSent(ProctorRequestDecided::class, fn ($m) => $m->hasTo('daniel@example.com') && ! str_contains($m->render(), $code->code) && str_contains($m->render(), 'approved'));

        // The student never sees the code, and can start only with it.
        $this->actingAs($this->student)->get(route('proctor-requests.create', [$this->course, $this->exam]))->assertSee('your proctor has the code')->assertDontSee($code->code);
        $this->post(route('quiz.start', [$this->course, $this->exam]), ['access_code' => $code->code])->assertRedirect();
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_decline_needs_a_reason_and_tells_the_student(): void
    {
        $this->submitRequest();
        $r = ProctorRequest::first();
        $this->actingAs($this->course->instructor)->post(route('studio.proctoring.decline', $r), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('studio.proctoring.decline', $r), ['reason' => 'The proctor cannot be a relative.'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $r->fresh()->status);
        $this->assertSame(0, ExamAccessCode::count());
        Mail::assertSent(ProctorRequestDecided::class, fn ($m) => $m->hasTo('daniel@example.com') && str_contains($m->render(), 'cannot be a relative'));

        $this->actingAs($this->student)->get(route('proctor-requests.create', [$this->course, $this->exam]))->assertSee('declined')->assertSee('Send a new request');
    }

    public function test_proctor_page_shows_the_code_and_records_the_confirmation(): void
    {
        $this->submitRequest();
        $token = $this->approve();
        $code = ProctorRequest::first()->code;

        auth()->logout();
        $this->get(route('proctor.show', $token))->assertOk()->assertSee($code->code)->assertSee('Daniel Reyes')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('once the student has started');
        $this->post(route('proctor.confirm', $token), ['supervised' => '1'])->assertStatus(409);

        // The student starts with the code: the page stops showing it.
        $this->actingAs($this->student)->post(route('quiz.start', [$this->course, $this->exam]), ['access_code' => $code->code]);
        auth()->logout();
        $this->get(route('proctor.show', $token))->assertOk()->assertDontSee($code->code)->assertSee('Exam in progress');

        $this->post(route('proctor.confirm', $token), ['flagged' => '1'])->assertSessionHasErrors(['supervised', 'report']);
        $this->post(route('proctor.confirm', $token), ['supervised' => '1', 'flagged' => '1', 'report' => 'A second person was in the room.'])->assertRedirect(route('proctor.show', $token));
        $r = ProctorRequest::first();
        $this->assertTrue($r->proctor_flagged);
        $this->assertNotNull($r->proctor_confirmed_at);

        $this->actingAs($this->course->instructor)->get(route('studio.proctoring', ['status' => 'approved']))->assertSee('Proctor reported a problem')->assertSee('A second person was in the room.');
        $this->get(route('studio.students.show', [$this->course, $this->enrollment]))->assertSee('Proctor reported a problem');
    }

    public function test_bad_or_replaced_links_do_not_work(): void
    {
        $this->get(route('proctor.show', str_repeat('x', 48)))->assertNotFound();
        $this->get(route('proctor.show', 'short'))->assertNotFound();

        $this->submitRequest();
        $old = $this->approve();
        $this->actingAs($this->course->instructor)->post(route('studio.proctoring.resend', ProctorRequest::first()))->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('proctor.show', $old))->assertNotFound();
        $this->assertSame(2, Mail::sent(ProctorAssigned::class)->count());
        $new = Mail::sent(ProctorAssigned::class)->last()->token;
        $this->get(route('proctor.show', $new))->assertOk();
    }

    public function test_other_instructors_cannot_see_or_decide(): void
    {
        $this->submitRequest();
        $other = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($other)->get(route('studio.proctoring'))->assertOk()->assertDontSee('Pat Proctor');
        $this->post(route('studio.proctoring.approve', ProctorRequest::first()), ['valid_hours' => 72])->assertForbidden();
        $this->post(route('studio.proctoring.decline', ProctorRequest::first()), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->student)->get(route('studio.proctoring'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('studio.proctoring'))->assertSee('Pat Proctor');
        $this->get(route('studio.dashboard'))->assertSee('aria-label="1 waiting"', false);
    }

    public function test_staff_can_email_a_hand_made_code_to_the_proctor(): void
    {
        $this->actingAs($this->course->instructor)->post(route('studio.exam-codes.store', [$this->course, $this->enrollment]), [
            'quiz_id' => $this->quiz->id, 'valid_hours' => 24, 'proctor_name' => 'Sam Smith', 'email_proctor' => '1',
        ])->assertSessionHasErrors('proctor_email');

        $this->post(route('studio.exam-codes.store', [$this->course, $this->enrollment]), [
            'quiz_id' => $this->quiz->id, 'valid_hours' => 24, 'proctor_name' => 'Sam Smith', 'proctor_email' => 'sam@example.com', 'email_proctor' => '1',
        ])->assertSessionHas('status', 'Code created and emailed to the proctor. The student was told too.');

        $r = ProctorRequest::first();
        $this->assertSame(['approved', 'sam@example.com'], [$r->status, $r->proctor_email]);
        Mail::assertSent(ProctorAssigned::class, fn ($m) => $m->hasTo('sam@example.com'));
        Mail::assertSent(ProctorRequestDecided::class, fn ($m) => $m->hasTo('daniel@example.com'));
    }
}
