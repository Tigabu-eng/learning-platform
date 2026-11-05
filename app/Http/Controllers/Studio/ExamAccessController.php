<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAccessCode;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** One-time exam codes, made by staff for a student and sent to that student's proctor. */
class ExamAccessController extends Controller
{
    public const VALID_FOR_HOURS = [24 => '24 hours', 72 => '3 days', 168 => '1 week', 336 => '2 weeks'];

    public function store(Request $request, Course $course, Enrollment $enrollment)
    {
        CourseController::authorizeCourse($request, $course);
        abort_unless($enrollment->course_id === $course->id, 404);
        $data = $request->validate([
            'quiz_id' => ['required', Rule::in(self::codeQuizzes($course)->pluck('id')->all())],
            'valid_hours' => ['required', Rule::in(array_keys(self::VALID_FOR_HOURS))],
            'proctor_name' => 'nullable|string|max:120',
            'proctor_email' => 'nullable|email|max:190',
            'notes' => 'nullable|string|max:2000',
        ], ['quiz_id.in' => 'That exam isn’t set to use one-time codes.']);

        $code = ExamAccessCode::create([
            'quiz_id' => $data['quiz_id'],
            'enrollment_id' => $enrollment->id,
            'code' => ExamAccessCode::generate(),
            'proctor_name' => $data['proctor_name'] ?? null,
            'proctor_email' => $data['proctor_email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'expires_at' => now()->addHours((int) $data['valid_hours']),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->to(route('studio.students.show', [$course, $enrollment]).'#exam-codes')
            ->with('status', 'Code created. Copy it into your email to the proctor.')
            ->with('new_code', $code->id);
    }

    public function revoke(Request $request, ExamAccessCode $code)
    {
        $course = $code->enrollment->course;
        CourseController::authorizeCourse($request, $course);
        if ($code->isActive()) $code->update(['revoked_at' => now()]);
        return redirect()->to(route('studio.students.show', [$course, $code->enrollment]).'#exam-codes')->with('status', 'Code cancelled. It can no longer be used.');
    }

    /** Quizzes and exams in the course that use one-time codes. */
    public static function codeQuizzes(Course $course)
    {
        return Quiz::whereIn('lesson_id', $course->lessons()->select('id'))->where('access_mode', 'code')->with('lesson')->get();
    }
}
