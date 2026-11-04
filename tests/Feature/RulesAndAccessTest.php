<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesAndAccessTest extends TestCase
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
        $c = Course::create(array_merge(['instructor_id' => $i->id, 'title' => 'Test course', 'status' => 'published'], $attrs));
        $ch = $c->chapters()->create(['title' => 'One', 'position' => 1]);
        foreach (['A', 'B', 'C'] as $n => $t) $ch->lessons()->create(['course_id' => $c->id, 'title' => $t, 'type' => 'text', 'position' => $n + 1]);
        return $c;
    }

    public function test_sequential_courses_lock_later_lessons(): void
    {
        $c = $this->course(['sequential' => true]);
        $s = User::factory()->create();
        app(Progress::class)->enroll($s, $c);
        [$a, $b] = $c->orderedLessons()->all();
        $this->actingAs($s)->get(route('learn.lesson', [$c, $b]))->assertSee('Complete &quot;A&quot; first', false);
        $this->post(route('learn.complete', [$c, $b]))->assertForbidden();
        $this->post(route('learn.complete', [$c, $a]))->assertRedirect(route('learn.lesson', [$c, $b]));
        $this->get(route('learn.lesson', [$c, $b]))->assertDontSee('This lesson is locked');
    }

    public function test_drip_lessons_open_after_n_days(): void
    {
        $c = $this->course(['drip_enabled' => true]);
        $b = $c->orderedLessons()[1];
        $b->update(['drip_days' => 3]);
        $s = User::factory()->create();
        $e = app(Progress::class)->enroll($s, $c);
        $this->actingAs($s)->get(route('learn.lesson', [$c, $b]))->assertSee('Available on');
        $e->update(['enrolled_at' => now()->subDays(4)]);
        $this->get(route('learn.lesson', [$c, $b]))->assertDontSee('This lesson is locked');
    }

    public function test_non_enrolled_users_only_see_previews(): void
    {
        $c = $this->course();
        [$a, $b] = $c->orderedLessons()->all();
        $a->update(['is_preview' => true]);
        $s = User::factory()->create();
        $this->actingAs($s)->get(route('learn.lesson', [$c, $a]))->assertOk()->assertSee('A');
        $this->get(route('learn.lesson', [$c, $b]))->assertRedirect(route('courses.show', $c));
    }

    public function test_attempt_limit_and_timer_expiry(): void
    {
        $c = $this->course();
        $l = $c->chapters()->first()->lessons()->create(['course_id' => $c->id, 'title' => 'Q', 'type' => 'quiz', 'position' => 9]);
        $quiz = Quiz::create(['lesson_id' => $l->id, 'passing_score' => 100, 'max_attempts' => 1, 'time_limit_minutes' => 5]);
        $q = $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Sky is blue', 'points' => 1]);
        $q->options()->createMany([['text' => 'True', 'is_correct' => true, 'position' => 1], ['text' => 'False', 'is_correct' => false, 'position' => 2]]);
        $s = User::factory()->create();
        app(Progress::class)->enroll($s, $c);

        $this->actingAs($s)->post(route('quiz.start', [$c, $l]));
        $attempt = QuizAttempt::first();
        $this->post(route('attempts.autosave', $attempt), ['answers' => [$q->id => (string) $q->options[1]->id]])->assertJson(['saved' => true]);

        // Time runs out while away → auto-graded with saved answers
        $this->travel(6)->minutes();
        $this->get(route('attempts.show', $attempt))->assertOk()->assertSee('Not passed yet');
        $this->assertNotNull($attempt->fresh()->submitted_at);

        // No attempts left
        $this->post(route('quiz.start', [$c, $l]))->assertSessionHasErrors('quiz');
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_grading_rules_for_each_question_type(): void
    {
        $c = $this->course();
        $l = $c->chapters()->first()->lessons()->create(['course_id' => $c->id, 'title' => 'Q', 'type' => 'quiz', 'position' => 9]);
        $quiz = Quiz::create(['lesson_id' => $l->id]);
        $multi = $quiz->questions()->create(['type' => 'multiple', 'prompt' => 'm', 'points' => 1]);
        $o = $multi->options()->createMany([['text' => 'a', 'is_correct' => true], ['text' => 'b', 'is_correct' => true], ['text' => 'c', 'is_correct' => false]]);
        $multi->load('options');
        $this->assertTrue($multi->grade([(string) $o[1]->id, (string) $o[0]->id]));
        $this->assertFalse($multi->grade([(string) $o[0]->id]), 'partial selection is wrong');
        $this->assertFalse($multi->grade([(string) $o[0]->id, (string) $o[1]->id, (string) $o[2]->id]), 'extra selection is wrong');

        $short = $quiz->questions()->create(['type' => 'short', 'prompt' => 's', 'accepted_answers' => "Addis Ababa\nFinfinne"]);
        $this->assertTrue($short->grade('  addis   ababa '));
        $this->assertTrue($short->grade('FINFINNE'));
        $this->assertFalse($short->grade('Adama'));
    }

    public function test_instructors_cannot_touch_other_instructors_courses(): void
    {
        $c = $this->course();
        $other = User::factory()->create(['role' => 'instructor']);
        $lesson = $c->lessons()->first();
        $this->actingAs($other)->get(route('studio.curriculum', $c))->assertForbidden();
        $this->get(route('studio.lessons.edit', $lesson))->assertForbidden();
        $this->post(route('studio.courses.publish', $c))->assertForbidden();
        $this->postJson(route('studio.curriculum.reorder', $c), ['chapters' => []])->assertForbidden();

        $student = User::factory()->create();
        $this->actingAs($student)->get(route('studio.dashboard'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('studio.curriculum', $c))->assertOk();
    }

    public function test_reorder_moves_lessons_between_chapters(): void
    {
        $c = $this->course();
        $one = $c->chapters()->first();
        $two = $c->chapters()->create(['title' => 'Two', 'position' => 2]);
        [$a, $b, $cc] = $c->orderedLessons()->all();
        $this->actingAs($c->instructor)->postJson(route('studio.curriculum.reorder', $c), ['chapters' => [
            ['id' => $two->id, 'lessons' => [$cc->id, $a->id]],
            ['id' => $one->id, 'lessons' => [$b->id]],
        ]])->assertJson(['saved' => true]);
        $this->assertSame(['C', 'A', 'B'], $c->orderedLessons()->pluck('title')->all());
    }

    public function test_question_builder_validates_correct_answers(): void
    {
        $c = $this->course();
        $this->actingAs($c->instructor)->post(route('studio.lessons.store', $c->chapters()->first()), ['title' => 'Quiz', 'type' => 'quiz']);
        $l = Lesson::firstWhere('title', 'Quiz');
        $this->post(route('studio.questions.store', $l), ['type' => 'single', 'prompt' => 'x', 'points' => 1, 'options' => [['text' => 'a'], ['text' => 'b']]])
            ->assertSessionHasErrors('correct');
        $this->post(route('studio.questions.store', $l), ['type' => 'single', 'prompt' => 'x', 'points' => 1, 'options' => [['text' => 'a'], ['text' => 'b']], 'correct' => ['0', '1']])
            ->assertSessionHasErrors('correct');
        $this->post(route('studio.questions.store', $l), ['type' => 'single', 'prompt' => 'x', 'points' => 1, 'options' => [['text' => 'a']], 'correct' => ['0']])
            ->assertSessionHasErrors('options');
        $this->assertSame(0, $l->quiz->questions()->count());
    }

    public function test_only_one_final_exam_per_course(): void
    {
        $c = $this->course();
        $ch = $c->chapters()->first();
        $this->actingAs($c->instructor)->post(route('studio.lessons.store', $ch), ['title' => 'Exam 1', 'type' => 'exam'])->assertRedirect();
        $this->post(route('studio.lessons.store', $ch), ['title' => 'Exam 2', 'type' => 'exam'])->assertSessionHasErrors('type');
    }

    public function test_seeded_demo_school_works(): void
    {
        $this->seed();
        $aisha = User::firstWhere('email', 'aisha@tarc.test');
        $this->assertCount(1, $aisha->certificates);
        $this->actingAs($aisha)->get(route('dashboard'))->assertOk()->assertSee('View certificate');
        $daniel = User::firstWhere('email', 'daniel@tarc.test');
        $e = $daniel->enrollments()->first();
        $this->assertGreaterThan(0, $e->progress_percent);
        $this->assertLessThan(100, $e->progress_percent);
    }
}
