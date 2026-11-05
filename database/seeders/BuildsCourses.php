<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Quiz;

/** Shared helpers for seeders that build courses. */
trait BuildsCourses
{
    protected function lesson($chapter, string $type, string $title, array $extra = []): Lesson
    {
        return $chapter->lessons()->create(array_merge([
            'course_id' => $chapter->course_id, 'type' => $type, 'title' => $title,
            'position' => ($chapter->lessons()->max('position') ?? 0) + 1,
        ], $extra));
    }

    /** $questions: [prompt, type, options|accepted answers, correct index(es), explanation] */
    protected function quiz(Lesson $lesson, array $settings, array $questions): Quiz
    {
        $quiz = Quiz::create(['lesson_id' => $lesson->id] + $settings);
        foreach ($questions as $i => [$prompt, $type, $choices, $correct, $why]) {
            $q = $quiz->questions()->create([
                'type' => $type, 'prompt' => $prompt, 'explanation' => $why, 'points' => 1, 'position' => $i + 1,
                'accepted_answers' => $type === 'short' ? implode("\n", $choices) : null,
            ]);
            if ($type === 'true_false') $choices = ['True', 'False'];
            if ($type !== 'short') {
                foreach ($choices as $j => $text) {
                    $q->options()->create(['text' => $text, 'is_correct' => in_array($j, (array) $correct, true), 'position' => $j + 1]);
                }
            }
        }
        return $quiz;
    }
}
