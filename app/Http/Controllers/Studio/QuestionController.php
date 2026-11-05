<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\QuestionImporter;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function store(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        abort_unless($lesson->quiz, 404);
        $quiz = $lesson->quiz;
        $question = DB::transaction(function () use ($request, $quiz) {
            $q = $quiz->questions()->create($this->fields($request) + ['position' => ($quiz->questions()->max('position') ?? 0) + 1]);
            $this->syncOptions($request, $q);
            return $q;
        });
        return redirect()->to(route('studio.lessons.edit', $lesson).'#q'.$question->id)->with('status', 'Question added.');
    }

    public function update(Request $request, Question $question)
    {
        $lesson = $question->quiz->lesson;
        CourseController::authorizeCourse($request, $lesson->course);
        DB::transaction(function () use ($request, $question) {
            $question->update($this->fields($request));
            $this->syncOptions($request, $question);
        });
        return redirect()->to(route('studio.lessons.edit', $lesson).'#q'.$question->id)->with('status', 'Question saved.');
    }

    public function destroy(Request $request, Question $question)
    {
        $lesson = $question->quiz->lesson;
        CourseController::authorizeCourse($request, $lesson->course);
        $question->delete();
        return redirect()->route('studio.lessons.edit', $lesson)->with('status', 'Question deleted.');
    }

    public function reorder(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        $ids = $lesson->quiz->questions()->pluck('id')->map(fn ($i) => (int) $i)->all();
        foreach (array_values((array) $request->input('order')) as $i => $id) {
            if (in_array((int) $id, $ids, true)) Question::whereKey($id)->update(['position' => $i + 1]);
        }
        return response()->json(['saved' => true]);
    }

    private function fields(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'topic' => 'nullable|string|max:120',
            'prompt' => 'required|string|max:20000',
            'explanation' => 'nullable|string|max:20000',
            'points' => 'required|integer|min:1|max:100',
            'accepted_answers' => 'nullable|required_if:type,short|string|max:2000',
        ], ['accepted_answers.required_if' => 'Add at least one accepted answer.']);
        if (RichText::plain(RichText::clean($data['prompt'])) === '' && ! str_contains($data['prompt'], '<img')) {
            throw ValidationException::withMessages(['prompt' => 'Write the question.']);
        }
        $data['topic'] = QuestionImporter::normalizeTopic($data['topic'] ?? null) ?: null;
        return $data;
    }

    private function syncOptions(Request $request, Question $q): void
    {
        $q->options()->delete();

        if ($q->type === 'short') return;

        if ($q->type === 'true_false') {
            $answer = $request->input('tf_answer', 'true') === 'true';
            $q->options()->createMany([
                ['text' => 'True', 'is_correct' => $answer, 'position' => 1],
                ['text' => 'False', 'is_correct' => ! $answer, 'position' => 2],
            ]);
            return;
        }

        $rows = collect((array) $request->input('options'))
            ->map(fn ($o, $i) => ['text' => trim((string) ($o['text'] ?? '')), 'key' => (string) $i])
            ->filter(fn ($o) => $o['text'] !== '')->values();
        $correct = collect((array) $request->input('correct'))->map(fn ($c) => (string) $c)->all();

        if ($rows->count() < 2) throw ValidationException::withMessages(['options' => 'Add at least two answer choices.']);
        $marked = $rows->filter(fn ($o) => in_array($o['key'], $correct, true));
        if ($marked->isEmpty()) throw ValidationException::withMessages(['correct' => 'Mark the correct answer.']);
        if ($q->type === 'single' && $marked->count() > 1) throw ValidationException::withMessages(['correct' => 'One-answer questions can only have one correct choice.']);

        foreach ($rows as $i => $o) {
            $q->options()->create(['text' => $o['text'], 'is_correct' => in_array($o['key'], $correct, true), 'position' => $i + 1]);
        }
    }
}
