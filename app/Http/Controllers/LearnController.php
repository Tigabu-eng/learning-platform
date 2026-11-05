<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Services\Progress;
use App\Services\QuizEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LearnController extends Controller
{
    public function __construct(private Progress $progress, private QuizEngine $quizzes) {}

    public function resume(Request $request, Course $course)
    {
        $enrollment = $request->user()->enrollmentFor($course);
        $ordered = $course->orderedLessons();
        if ($ordered->isEmpty()) return redirect()->route('courses.show', $course)->with('status', 'This course has no lessons yet.');

        $target = $enrollment?->lastLesson;
        if (! $target && $enrollment) {
            $done = $enrollment->completedLessonIds();
            $target = $ordered->first(fn ($l) => ! in_array($l->id, $done, true));
        }
        return redirect()->route('learn.lesson', [$course, $target ?? $ordered->first()]);
    }

    public function show(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $user = $request->user();
        $manager = $course->canBeManagedBy($user);
        $enrollment = $user->enrollmentFor($course);

        if (! $enrollment && ! $manager && ! $lesson->is_preview) {
            return redirect()->route('courses.show', $course)->with('status', 'Enroll to start learning.');
        }

        $course->load('chapters.lessons.quiz');
        $ordered = $course->chapters->flatMap->lessons->values();
        $done = $enrollment ? $enrollment->completedLessonIds() : [];
        $locks = [];
        foreach ($ordered as $l) {
            $l->setRelation('course', $course);
            $locks[$l->id] = $manager ? null : $this->progress->lockReason($l, $enrollment, $ordered, $done);
        }

        $index = $ordered->search(fn ($l) => $l->id === $lesson->id);
        $prev = $index > 0 ? $ordered[$index - 1] : null;
        $next = $ordered[$index + 1] ?? null;
        $lesson = $ordered[$index];

        if ($enrollment && ! $locks[$lesson->id]) {
            $enrollment->update(['last_lesson_id' => $lesson->id, 'last_activity_at' => now()]);
        }

        $quizInfo = null;
        if ($lesson->isAssessment() && $lesson->quiz) {
            $quiz = $lesson->quiz;
            $attempts = $enrollment ? $quiz->attempts()->where('enrollment_id', $enrollment->id)->whereNotNull('submitted_at')->latest()->get() : collect();
            $open = $enrollment ? $quiz->attempts()->where('enrollment_id', $enrollment->id)->whereNull('submitted_at')->latest()->first() : null;
            $quizInfo = [
                'quiz' => $quiz,
                'questions' => $quiz->attemptSize(),
                'points' => $quiz->totalPoints(),
                'attempts' => $attempts,
                'open' => $open && (! $open->expires_at || $open->expires_at->isFuture()) ? $open : null,
                'used' => $attempts->count(),
                'canAttempt' => $enrollment ? $this->quizzes->canAttempt($quiz, $enrollment) : false,
                'best' => $attempts->max('score'),
                'passed' => $attempts->contains('passed', true),
            ];
        }

        return view('learn.show', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'manager' => $manager,
            'locks' => $locks,
            'done' => $done,
            'prev' => $prev,
            'next' => $next,
            'quizInfo' => $quizInfo,
            'certificate' => $enrollment?->certificate,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $enrollment = $request->user()->enrollmentFor($course);
        abort_unless($enrollment, 403);
        abort_if($lesson->isAssessment(), 422, 'Quizzes are completed by passing them.');
        abort_if($this->progress->lockReason($lesson, $enrollment), 403);

        $this->progress->complete($enrollment, $lesson);

        $ordered = $course->orderedLessons();
        $index = $ordered->search(fn ($l) => $l->id === $lesson->id);
        $next = $ordered[$index + 1] ?? null;
        $enrollment->refresh();

        if ($enrollment->certificate) {
            return redirect()->route('learn.lesson', [$course, $lesson])->with('celebrate', true);
        }
        return $next
            ? redirect()->route('learn.lesson', [$course, $next])
            : redirect()->route('learn.lesson', [$course, $lesson])->with('status', 'Lesson complete.');
    }

    public function download(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id && $lesson->file_path, 404);
        $enrollment = $request->user()->enrollmentFor($course);
        $allowed = $course->canBeManagedBy($request->user()) || ($enrollment && ! $this->progress->lockReason($lesson, $enrollment)) || $lesson->is_preview;
        abort_unless($allowed, 403);
        return Storage::disk('local')->download($lesson->file_path, $lesson->file_name);
    }
}
