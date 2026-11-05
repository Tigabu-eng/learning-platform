<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAccessCode;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamAccessTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Lesson $exam;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $instructor = User::factory()->create(['role' => 'instructor']);
        $this->course = Course::create(['instructor_id' => $instructor->id, 'title' => 'RTA', 'status' => 'published']);
        $chapter = $this->course->chapters()->create(['title' => 'Final', 'position' => 1]);
        $this->exam = $chapter->lessons()->create(['course_id' => $this->course->id, 'title' => 'RTA final exam', 'type' => 'exam', 'position' => 1]);
        $this->quiz = Quiz::create(['lesson_id' => $this->exam->id, 'is_final' => true, 'max_attempts' => 3]);
        $q = $this->quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q', 'points' => 1]);
        $q->options()->createMany([['text' => 'True', 'is_correct' => true, 'position' => 1], ['text' => 'False', 'is_correct' => false, 'position' => 2]]);
    }

    private function student(): array
    {
        $s = User::factory()->create();
        return [$s, app(Progress::class)->enroll($s, $this->course)];
    }

    private function start(User $user, ?string $code = null)
    {
        return $this->actingAs($user)->from(route('learn.lesson', [$this->course, $this->exam]))
            ->post(route('quiz.start', [$this->course, $this->exam]), $code === null ? [] : ['access_code' => $code]);
    }

    private function codeFor(Enrollment $e, array $attrs = []): ExamAccessCode
    {
        return ExamAccessCode::create(array_merge([
            'quiz_id' => $this->quiz->id, 'enrollment_id' => $e->id, 'code' => 'ABCD-EFGH', 'expires_at' => now()->addDay(),
        ], $attrs));
    }

    public function test_open_exams_start_without_a_password(): void
    {
        [$s] = $this->student();
        $this->start($s)->assertRedirect(route('attempts.show', QuizAttempt::first()));
    }

    public function test_shared_password(): void
    {
        $this->quiz->update(['access_mode' => 'password', 'access_password' => 'Sunrise-2026']);
        [$s] = $this->student();
        $this->actingAs($s)->get(route('learn.lesson', [$this->course, $this->exam]))->assertSee('Exam password');

        $this->start($s)->assertSessionHasErrors('access_code');
        $this->start($s, 'sunrise-2026')->assertSessionHasErrors('access_code'); // case-sensitive
        $this->assertSame(0, QuizAttempt::count());

        $this->start($s, ' Sunrise-2026 ')->assertRedirect(route('attempts.show', QuizAttempt::first()));
        // Resuming the open attempt doesn't ask again.
        $this->start($s)->assertRedirect(route('attempts.show', QuizAttempt::first()));
        $this->assertSame(1, QuizAttempt::count());

        $this->assertStringNotContainsString('Sunrise', (string) DB::table('quizzes')->value('access_password'), 'stored encrypted');
    }

    public function test_wrong_guesses_are_rate_limited(): void
    {
        $this->quiz->update(['access_mode' => 'password', 'access_password' => 'right']);
        [$s] = $this->student();
        foreach (range(1, 5) as $_) $this->start($s, 'wrong');
        $this->start($s, 'right')->assertSessionHasErrors(['access_code' => 'Too many wrong tries. Wait 15 minutes, then try again.']);
        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_one_time_codes_work_once_for_one_student(): void
    {
        $this->quiz->update(['access_mode' => 'code']);
        [$s, $e] = $this->student();
        [$other] = $this->student();
        $code = $this->codeFor($e);
        $this->actingAs($s)->get(route('learn.lesson', [$this->course, $this->exam]))->assertSee('Proctor access code');

        $this->start($other, 'ABCD-EFGH')->assertSessionHasErrors('access_code'); // someone else's code
        $this->start($s, 'abcd efgh')->assertRedirect(); // spacing and case don't matter

        $attempt = QuizAttempt::firstWhere('user_id', $s->id);
        $this->assertNotNull($attempt);
        $code->refresh();
        $this->assertSame(['used', $attempt->id], [$code->status(), $code->quiz_attempt_id]);

        // Finish, then try the same code for a retake.
        $this->post(route('attempts.submit', $attempt), ['answers' => []]);
        $this->start($s, 'ABCD-EFGH')->assertSessionHasErrors('access_code');
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_expired_and_cancelled_codes_fail(): void
    {
        $this->quiz->update(['access_mode' => 'code']);
        [$s, $e] = $this->student();
        $this->codeFor($e, ['code' => 'AAAA-BBBB', 'expires_at' => now()->subMinute()]);
        $this->codeFor($e, ['code' => 'CCCC-DDDD', 'revoked_at' => now()]);
        $this->start($s, 'AAAA-BBBB')->assertSessionHasErrors('access_code');
        $this->start($s, 'CCCC-DDDD')->assertSessionHasErrors('access_code');
        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_staff_generate_and_cancel_codes(): void
    {
        $this->quiz->update(['access_mode' => 'code']);
        [$s, $e] = $this->student();
        $staff = $this->course->instructor;

        $this->actingAs($staff)->get(route('studio.students.show', [$this->course, $e]))->assertOk()->assertSee('Generate code');
        $this->post(route('studio.exam-codes.store', [$this->course, $e]), [
            'quiz_id' => $this->quiz->id, 'valid_hours' => 72, 'proctor_name' => 'Pat Proctor', 'proctor_email' => 'pat@example.com', 'notes' => 'Zoom',
        ])->assertRedirect()->assertSessionHas('new_code');

        $code = ExamAccessCode::first();
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code->code);
        $this->assertEqualsWithDelta(now()->addHours(72)->timestamp, $code->expires_at->timestamp, 5);
        $this->assertSame([$staff->id, 'Pat Proctor'], [$code->created_by, $code->proctor_name]);
        $this->get(route('studio.students.show', [$this->course, $e]))->assertSee($code->code)->assertSee('Access code: '.$code->code);

        $this->post(route('studio.exam-codes.revoke', $code))->assertRedirect();
        $this->assertSame('revoked', $code->fresh()->status());
        $this->start($s, $code->code)->assertSessionHasErrors('access_code');

        // Codes only for exams set to use them.
        $this->quiz->update(['access_mode' => 'password', 'access_password' => 'x']);
        $this->actingAs($staff)->post(route('studio.exam-codes.store', [$this->course, $e]), ['quiz_id' => $this->quiz->id, 'valid_hours' => 24])
            ->assertSessionHasErrors('quiz_id');
    }

    public function test_other_instructors_cannot_manage_codes(): void
    {
        $this->quiz->update(['access_mode' => 'code']);
        [, $e] = $this->student();
        $code = $this->codeFor($e);
        $other = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($other)->post(route('studio.exam-codes.store', [$this->course, $e]), ['quiz_id' => $this->quiz->id, 'valid_hours' => 24])->assertForbidden();
        $this->post(route('studio.exam-codes.revoke', $code))->assertForbidden();
        $this->assertSame(1, ExamAccessCode::count());
    }

    public function test_course_staff_preview_without_a_password(): void
    {
        $this->quiz->update(['access_mode' => 'code']);
        $staff = $this->course->instructor;
        app(Progress::class)->enroll($staff, $this->course);
        $this->actingAs($staff)->get(route('learn.lesson', [$this->course, $this->exam]))->assertDontSee('Proctor access code');
        $this->start($staff)->assertRedirect(route('attempts.show', QuizAttempt::first()));
    }

    public function test_studio_saves_access_settings(): void
    {
        $staff = $this->course->instructor;
        $base = ['title' => 'RTA final exam', 'chapter_id' => $this->exam->chapter_id, 'passing_score' => 75];
        $this->actingAs($staff)->put(route('studio.lessons.update', $this->exam), $base + ['access_mode' => 'password'])
            ->assertSessionHasErrors('access_password');
        $this->put(route('studio.lessons.update', $this->exam), $base + ['access_mode' => 'password', 'access_password' => 'Sunrise'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['password', 'Sunrise'], [$this->quiz->fresh()->access_mode, $this->quiz->fresh()->access_password]);
        $this->get(route('studio.lessons.edit', $this->exam))->assertSee('value="Sunrise"', false);

        $this->put(route('studio.lessons.update', $this->exam), $base + ['access_mode' => 'code'])->assertSessionHasNoErrors();
        $this->assertSame('code', $this->quiz->fresh()->access_mode);
        $this->put(route('studio.lessons.update', $this->exam), $base + ['access_mode' => 'nonsense'])->assertSessionHasErrors('access_mode');
    }
}
