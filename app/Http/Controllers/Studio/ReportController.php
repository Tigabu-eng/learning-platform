<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function students(Request $request, Course $course)
    {
        CourseController::authorizeCourse($request, $course);
        $final = $course->finalExam();
        $enrollments = $course->enrollments()->with(['user', 'certificate'])
            ->when($request->q, fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->latest('enrolled_at')->get();
        $finalScores = $final
            ? QuizAttempt::where('quiz_id', $final->id)->whereNotNull('submitted_at')->selectRaw('enrollment_id, max(score) as best, max(passed) as passed')->groupBy('enrollment_id')->get()->keyBy('enrollment_id')
            : collect();
        return view('studio.students', compact('course', 'enrollments', 'final', 'finalScores'));
    }

    public function enrollStudent(Request $request, Course $course, Progress $progress)
    {
        CourseController::authorizeCourse($request, $course);
        $data = $request->validate(['email' => 'required|email', 'name' => 'nullable|string|max:120']);
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            if (empty($data['name'])) return back()->withErrors(['email' => 'No account uses that email. Add their name to create one.'])->withInput();
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => str()->random(32), 'role' => 'student']);
        }
        $progress->enroll($user, $course);
        return back()->with('status', $user->name.' is enrolled.');
    }

    public function student(Request $request, Course $course, Enrollment $enrollment)
    {
        CourseController::authorizeCourse($request, $course);
        abort_unless($enrollment->course_id === $course->id, 404);
        $course->load('chapters.lessons.quiz');
        $done = $enrollment->completions()->pluck('completed_at', 'lesson_id');
        $attempts = $enrollment->attempts()->whereNotNull('submitted_at')->with('quiz.lesson')->latest('submitted_at')->get();
        return view('studio.student', compact('course', 'enrollment', 'done', 'attempts'));
    }

    public function quizzes(Request $request, Course $course)
    {
        CourseController::authorizeCourse($request, $course);
        $lessons = $course->orderedLessons()->filter->isAssessment()->load('quiz.questions.options');
        $reports = $lessons->filter(fn ($l) => $l->quiz)->map(function ($lesson) {
            $quiz = $lesson->quiz;
            $attempts = $quiz->attempts()->whereNotNull('submitted_at')->get();
            $perQuestion = $quiz->questions->map(function ($q) use ($attempts) {
                $answered = $attempts->filter(fn ($a) => array_key_exists($q->id, $a->results ?? []));
                $right = $answered->filter(fn ($a) => ($a->results[$q->id] ?? false) === true)->count();
                return ['question' => $q, 'answered' => $answered->count(), 'rate' => $answered->count() ? (int) round($right / $answered->count() * 100) : null];
            });
            return [
                'lesson' => $lesson,
                'quiz' => $quiz,
                'attempts' => $attempts->count(),
                'students' => $attempts->pluck('enrollment_id')->unique()->count(),
                'average' => $attempts->count() ? (int) round($attempts->avg('score')) : null,
                'passRate' => $attempts->count() ? (int) round($attempts->where('passed', true)->pluck('enrollment_id')->unique()->count() / max(1, $attempts->pluck('enrollment_id')->unique()->count()) * 100) : null,
                'questions' => $perQuestion,
            ];
        });
        return view('studio.quiz-reports', compact('course', 'reports'));
    }
}
