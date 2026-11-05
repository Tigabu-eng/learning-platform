<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuestionImporter;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_formatting_is_kept_and_dangerous_html_removed(): void
    {
        $html = (string) RichText::html('<p><strong>Bold</strong> <em>it</em> <u>under</u> <a href="https://tarc.example">ok</a></p>'
            .'<p onclick="steal()">x<script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">bad</a></p>'
            .'<iframe src="https://evil.example"></iframe><h1>Big</h1>');

        foreach (['<strong>Bold</strong>', '<em>it</em>', '<u>under</u>', 'href="https://tarc.example"', 'rel="noopener noreferrer"', '<h2>Big</h2>'] as $kept) {
            $this->assertStringContainsString($kept, $html);
        }
        foreach (['<script', 'onclick', 'onerror', 'javascript:', '<iframe', '<h1'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
    }

    public function test_older_markdown_content_still_renders(): void
    {
        $html = (string) RichText::html("## Title\n\n**bold** and a [link](https://tarc.example)\n\n| a | b |\n|---|---|\n| 1 | 2 |");
        $this->assertStringContainsString('<h2>Title</h2>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<table>', $html);
        // and opens in the editor as HTML
        $this->assertStringStartsWith('<h2>', RichText::forEditor("## Title"));
    }

    public function test_plain_question_text_is_not_treated_as_markdown(): void
    {
        $html = (string) RichText::html("What is 2 * 3 * 4?\n<b>not bold</b>", false);
        $this->assertStringContainsString('2 * 3 * 4', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('<br />', $html);
    }

    public function test_saving_cleans_html_and_empty_editors_become_empty(): void
    {
        $i = User::factory()->create(['role' => 'instructor']);
        $course = Course::create(['instructor_id' => $i->id, 'title' => 'C', 'description' => '<p>Hi<script>x()</script></p>']);
        $this->assertSame('<p>Hi</p>', $course->description);

        $ch = $course->chapters()->create(['title' => 'One', 'position' => 1]);
        $lesson = $ch->lessons()->create(['course_id' => $course->id, 'title' => 'L', 'type' => 'text', 'position' => 1]);
        $this->withoutVite()->actingAs($i)->put(route('studio.lessons.update', $lesson), ['title' => 'L', 'chapter_id' => $ch->id, 'content' => '<p></p>'])
            ->assertSessionHasNoErrors();
        $this->assertNull($lesson->fresh()->content);

        $this->put(route('studio.lessons.update', $lesson), ['title' => 'L', 'chapter_id' => $ch->id, 'content' => '<p>Read <u>this</u></p>']);
        $this->assertSame('<p>Read <u>this</u></p>', $lesson->fresh()->content);
        $this->get(route('learn.lesson', [$course, $lesson]))->assertSee('<u>this</u>', false);
    }

    public function test_question_editor_fields(): void
    {
        $i = User::factory()->create(['role' => 'instructor']);
        $course = Course::create(['instructor_id' => $i->id, 'title' => 'C']);
        $ch = $course->chapters()->create(['title' => 'One', 'position' => 1]);
        $lesson = $ch->lessons()->create(['course_id' => $course->id, 'title' => 'Q', 'type' => 'quiz', 'position' => 1]);
        $quiz = Quiz::create(['lesson_id' => $lesson->id]);
        $this->withoutVite()->actingAs($i);

        $this->post(route('studio.questions.store', $lesson), ['type' => 'true_false', 'prompt' => '<p> </p>', 'points' => 1, 'tf_answer' => 'true'])
            ->assertSessionHasErrors(['prompt' => 'Write the question.']);

        $this->post(route('studio.questions.store', $lesson), ['type' => 'true_false', 'prompt' => '<p>Is <strong>IATA</strong> global?</p>', 'explanation' => '<p>Yes<script>x</script></p>', 'points' => 1, 'tf_answer' => 'true'])
            ->assertSessionHasNoErrors();
        $q = Question::first();
        $this->assertSame(['<p>Is <strong>IATA</strong> global?</p>', '<p>Yes</p>'], [$q->prompt, $q->explanation]);
        $this->get(route('studio.lessons.edit', $lesson))->assertSee('Is IATA global?');

        // Importing the same question as plain text is recognised as a duplicate.
        [$added, $skipped] = app(QuestionImporter::class)->import($quiz, app(QuestionImporter::class)->parseText("1. Is IATA global?\nAnswer: True"));
        $this->assertSame([0, 1], [$added, $skipped]);
    }
}
