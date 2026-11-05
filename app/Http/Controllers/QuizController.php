<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAccessCode;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Progress;
use App\Services\QuizEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

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
        // A password or proctor code is needed to begin a new attempt (not to resume one).
        // Instructors and admins previewing their own course skip it.
        if (! $open && $quiz->needsAccessCheck() && ! $course->canBeManagedBy($request->user())) {
            $attempt = $this->startWithAccessCheck($request, $quiz, $enrollment);
        } else {
            $attempt = $this->engine->start($quiz, $enrollment);
        }
        return redirect()->route('attempts.show', $attempt);
    }

    private function startWithAccessCheck(Request $request, Quiz $quiz, Enrollment $enrollment): QuizAttempt
    {
        $key = 'exam-access:'.$request->user()->id.':'.$quiz->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages(['access_code' => "Too many wrong tries. Wait $minutes minutes, then try again."]);
        }
        $given = (string) $request->input('access_code');

        if ($quiz->access_mode === 'password') {
            if ($quiz->access_password === null || $quiz->access_password === '' || ! hash_equals($quiz->access_password, trim($given))) {
                RateLimiter::hit($key, 15 * 60);
                throw ValidationException::withMessages(['access_code' => 'That password isn’t right. Check it with your proctor.']);
            }
            RateLimiter::clear($key);
            return $this->engine->start($quiz, $enrollment);
        }

        return DB::transaction(function () use ($quiz, $enrollment, $given, $key) {
            $wanted = ExamAccessCode::normalize($given);
            $match = $quiz->accessCodes()->where('enrollment_id', $enrollment->id)
                ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->lockForUpdate()->get()
                ->first(fn ($c) => $wanted !== '' && hash_equals(ExamAccessCode::normalize($c->code), $wanted));
            if (! $match) {
                RateLimiter::hit($key, 15 * 60);
                throw ValidationException::withMessages(['access_code' => 'That code isn’t valid. Codes work once, for one student, until they expire. Ask your proctor or TARC for a new one.']);
            }
            RateLimiter::clear($key);
            $attempt = $this->engine->start($quiz, $enrollment);
            $match->update(['used_at' => now(), 'quiz_attempt_id' => $attempt->id]);
            return $attempt;
        });
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
