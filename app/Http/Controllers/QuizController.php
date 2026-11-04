<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Services\Progress;
use App\Services\QuizEngine;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(private QuizEngine $engine, private Progress $progress) {}

    public function start(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id && $lesson->quiz, 404);
        $enrollment = $request->user()->enrollmentFor($course);
        abort_unless($enrollment, 403);
        if ($reason = $this->progress->lockReason($lesson, $enrollment)) {
            return back()->withErrors(['quiz' => $reason]);
        }
        $quiz = $lesson->quiz;
        $open = $quiz->attempts()->where('enrollment_id', $enrollment->id)->whereNull('submitted_at')->exists();
        if (! $open && ! $this->engine->canAttempt($quiz, $enrollment)) {
            return back()->withErrors(['quiz' => 'You’ve used all your attempts for this '.($quiz->is_final ? 'exam' : 'quiz').'.']);
        }
        $attempt = $this->engine->start($quiz, $enrollment);
        return redirect()->route('attempts.show', $attempt);
    }

    public function show(Request $request, QuizAttempt $attempt)
    {
        abort_unless($attempt->user_id === $request->user()->id || $attempt->quiz->lesson->course->canBeManagedBy($request->user()), 404);
        $quiz = $attempt->quiz()->with(['questions.options', 'lesson.course'])->first();

        // Close out a timed attempt that ran out while the student was away.
        if (! $attempt->isSubmitted() && $attempt->expires_at && $attempt->expires_at->isPast()) {
            $attempt = $this->engine->submit($attempt, $attempt->answers ?? []);
        }

        $questions = collect($attempt->question_order)->map(fn ($id) => $quiz->questions->firstWhere('id', $id))->filter()->values();
        if ($quiz->shuffle_options && ! $attempt->isSubmitted()) {
            $questions->each(function ($q) use ($attempt) {
                if ($q->type === 'true_false') return;
                mt_srand($attempt->id * 7919 + $q->id); // stable per attempt
                $opts = $q->options->all(); shuffle($opts); mt_srand();
                $q->setRelation('options', collect($opts));
            });
        }

        $view = $attempt->isSubmitted() ? 'quiz.result' : 'quiz.take';
        return view($view, ['attempt' => $attempt, 'quiz' => $quiz, 'questions' => $questions, 'course' => $quiz->lesson->course, 'lesson' => $quiz->lesson]);
    }

    public function autosave(Request $request, QuizAttempt $attempt)
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        if (! $attempt->isSubmitted()) $attempt->update(['answers' => (array) $request->input('answers', [])]);
        return response()->json(['saved' => true, 'seconds_left' => $attempt->secondsLeft()]);
    }

    public function submit(Request $request, QuizAttempt $attempt)
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        $attempt = $this->engine->submit($attempt, (array) $request->input('answers', []));
        return redirect()->route('attempts.show', $attempt);
    }
}
