<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** End-to-end: instructor builds a course, student buys it, learns, passes the exam, gets a certificate. */
class CourseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function buildCourse(User $instructor): array
    {
        $this->actingAs($instructor)->post(route('studio.courses.store'), ['title' => 'Coffee Roasting 101', 'price' => 1000])
            ->assertRedirect();
        $course = Course::firstWhere('title', 'Coffee Roasting 101');
        $chapter = $course->chapters()->first();

        $this->post(route('studio.lessons.store', $chapter), ['title' => 'Green beans', 'type' => 'text'])->assertRedirect();
        $text = Lesson::firstWhere('title', 'Green beans');
        $this->put(route('studio.lessons.update', $text), ['title' => 'Green beans', 'chapter_id' => $chapter->id, 'content' => '## Origins', 'is_preview' => 1])
            ->assertSessionHasNoErrors();

        $this->post(route('studio.lessons.store', $chapter), ['title' => 'Roast check', 'type' => 'quiz']);
        $quizLesson = Lesson::firstWhere('title', 'Roast check');
        $this->post(route('studio.questions.store', $quizLesson), [
            'type' => 'single', 'prompt' => 'First crack happens around?', 'points' => 1,
            'options' => [['text' => '196°C'], ['text' => '100°C'], ['text' => '300°C']], 'correct' => ['0'],
        ])->assertSessionHasNoErrors();
        $this->post(route('studio.questions.store', $quizLesson), [
            'type' => 'multiple', 'prompt' => 'Which are roast levels?', 'points' => 1,
            'options' => [['text' => 'Light'], ['text' => 'Dark'], ['text' => 'Wet']], 'correct' => ['0', '1'],
        ])->assertSessionHasNoErrors();

        $this->post(route('studio.lessons.store', $chapter), ['title' => 'Final exam', 'type' => 'exam']);
        $examLesson = Lesson::firstWhere('title', 'Final exam');
        $this->post(route('studio.questions.store', $examLesson), ['type' => 'true_false', 'prompt' => 'Roasting removes moisture.', 'points' => 2, 'tf_answer' => 'true'])->assertSessionHasNoErrors();
        $this->post(route('studio.questions.store', $examLesson), ['type' => 'short', 'prompt' => 'Ethiopia’s famous coffee region?', 'points' => 1, 'accepted_answers' => "Yirgacheffe\nSidamo"])->assertSessionHasNoErrors();

        $this->post(route('studio.courses.publish', $course))->assertSessionHasNoErrors();
        return [$course->fresh(), $text, $quizLesson, $examLesson];
    }

    public function test_full_student_journey_ends_with_a_verifiable_certificate(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        [$course, $text, $quizLesson, $examLesson] = $this->buildCourse($instructor);
        $this->assertTrue($course->isPublished());
        $this->assertTrue($examLesson->quiz->is_final);

        Coupon::create(['code' => 'HALF', 'type' => 'percent', 'value' => 50, 'created_by' => $instructor->id]);

        // Register and buy with coupon through the test gateway
        $this->post('/logout');
        $this->post(route('register'), ['name' => 'Liya Bekele', 'email' => 'liya@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect(route('dashboard'));
        $student = User::firstWhere('email', 'liya@example.com');

        $this->get(route('checkout.show', [$course, 'coupon' => 'half']))->assertOk()->assertSee('500.00');
        $res = $this->post(route('checkout.pay', $course), ['coupon' => 'HALF']);
        $order = Order::first();
        $res->assertRedirect(route('checkout.sandbox', $order));
        $this->assertEquals(500, (float) $order->total);
        $this->post(route('checkout.sandbox', $order), ['decision' => 'approve'])->assertRedirect(route('checkout.return', $order));
        $this->get(route('checkout.return', $order))->assertRedirect(route('learn.resume', $course));
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertEquals(1, Coupon::first()->uses);
        $enrollment = Enrollment::where('user_id', $student->id)->firstOrFail();

        // Exam is locked until lessons are done
        $this->get(route('learn.lesson', [$course, $examLesson]))->assertOk()->assertSee('Finish all lessons to unlock the final exam');
        $this->post(route('quiz.start', [$course, $examLesson]))->assertSessionHasErrors('quiz');

        // Text lesson
        $this->get(route('learn.lesson', [$course, $text]))->assertOk()->assertSee('Origins');
        $this->post(route('learn.complete', [$course, $text]))->assertRedirect();

        // Quiz: fail first (only one right), then pass
        $quiz = $quizLesson->quiz->load('questions.options');
        [$q1, $q2] = $quiz->questions->all();
        $right1 = (string) $q1->options->firstWhere('is_correct', true)->id;
        $right2 = $q2->options->where('is_correct', true)->pluck('id')->map(fn ($i) => (string) $i)->all();

        $this->post(route('quiz.start', [$course, $quizLesson]));
        $attempt = QuizAttempt::latest('id')->first();
        $this->post(route('attempts.submit', $attempt), ['answers' => [$q1->id => $right1, $q2->id => [$right2[0]]]]);
        $attempt->refresh();
        $this->assertSame(50, $attempt->score);
        $this->assertFalse($attempt->passed);
        $this->get(route('attempts.show', $attempt))->assertOk()->assertSee('Not passed yet');

        $this->post(route('quiz.start', [$course, $quizLesson]));
        $attempt = QuizAttempt::latest('id')->first();
        $this->post(route('attempts.submit', $attempt), ['answers' => [$q1->id => $right1, $q2->id => $right2]]);
        $this->assertTrue($attempt->fresh()->passed);
        $this->assertSame(66, $enrollment->fresh()->progress_percent);

        // Final exam now open — pass with short answer matched case-insensitively
        $exam = $examLesson->quiz->load('questions.options');
        $tf = $exam->questions->firstWhere('type', 'true_false');
        $short = $exam->questions->firstWhere('type', 'short');
        $this->post(route('quiz.start', [$course, $examLesson]))->assertRedirect();
        $attempt = QuizAttempt::where('quiz_id', $exam->id)->firstOrFail();
        $this->assertNotNull($attempt->expires_at, 'Exams default to a time limit');
        $this->post(route('attempts.submit', $attempt), ['answers' => [
            $tf->id => (string) $tf->options->firstWhere('text', 'True')->id,
            $short->id => '  yirgacheffe ',
        ]]);
        $this->assertSame(100, $attempt->fresh()->score);

        $enrollment->refresh();
        $this->assertSame(100, $enrollment->progress_percent);
        $this->assertNotNull($enrollment->completed_at);
        $cert = Certificate::firstOrFail();
        $this->assertSame(100, $cert->final_score);

        $this->get(route('certificates.show', $cert))->assertOk()->assertSee('Liya Bekele')->assertSee('Coffee Roasting 101');
        $this->get(route('certificates.verify', ['code' => strtolower($cert->code)]))->assertOk()->assertSee('Valid certificate');

        // Instructor reports reflect it
        $this->actingAs($instructor)->get(route('studio.students', $course))->assertOk()->assertSee('Liya Bekele')->assertSee('Issued');
        $this->get(route('studio.reports.quizzes', $course))->assertOk()->assertSee('First crack happens around?');
        $this->get(route('studio.dashboard'))->assertOk()->assertSee('500.00');
    }

    public function test_declined_payment_does_not_enroll(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        [$course] = $this->buildCourse($instructor);
        $student = User::factory()->create();
        $this->actingAs($student)->post(route('checkout.pay', $course));
        $order = Order::first();
        $this->post(route('checkout.sandbox', $order), ['decision' => 'decline']);
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.show', $course));
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertNull($student->enrollmentFor($course));
    }

    public function test_free_course_enrolls_immediately(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        [$course] = $this->buildCourse($instructor);
        $course->update(['price' => 0]);
        $student = User::factory()->create();
        $this->actingAs($student)->post(route('checkout.pay', $course))->assertRedirect(route('learn.resume', $course));
        $this->assertNotNull($student->enrollmentFor($course));
        $this->assertSame('free', Order::first()->gateway);
    }
}
