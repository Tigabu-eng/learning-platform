<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\ProctorRequest;
use App\Services\Progress;
use App\Services\Proctoring;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Students ask for a proctored exam and choose their proctor. */
class ProctorRequestController extends Controller
{
    public const TIMEZONES = [
        'America/New_York' => 'Eastern Time (US & Canada)',
        'America/Chicago' => 'Central Time (US & Canada)',
        'America/Denver' => 'Mountain Time (US & Canada)',
        'America/Phoenix' => 'Arizona',
        'America/Los_Angeles' => 'Pacific Time (US & Canada)',
        'America/Anchorage' => 'Alaska',
        'Pacific/Honolulu' => 'Hawaii',
        'America/Halifax' => 'Atlantic Time (Canada)',
        'America/Puerto_Rico' => 'Puerto Rico',
        'Europe/London' => 'London',
        'Europe/Paris' => 'Central Europe',
        'Africa/Addis_Ababa' => 'East Africa',
        'Asia/Dubai' => 'Gulf',
        'Asia/Kolkata' => 'India',
        'Australia/Sydney' => 'Sydney',
        'UTC' => 'UTC',
    ];

    public function __construct(private Proctoring $proctoring, private Progress $progress) {}

    public function create(Request $request, Course $course, Lesson $lesson)
    {
        [$enrollment, $quiz] = $this->context($request, $course, $lesson);
        $latest = ProctorRequest::where('quiz_id', $quiz->id)->where('enrollment_id', $enrollment->id)->latest('id')->first();
        return view('learn.proctor-request', [
            'course' => $course, 'lesson' => $lesson, 'quiz' => $quiz, 'latest' => $latest,
            'canRequest' => $this->canRequest($latest), 'timezones' => self::TIMEZONES,
        ]);
    }

    public function store(Request $request, Course $course, Lesson $lesson)
    {
        [$enrollment, $quiz] = $this->context($request, $course, $lesson);
        $latest = ProctorRequest::where('quiz_id', $quiz->id)->where('enrollment_id', $enrollment->id)->latest('id')->first();
        abort_unless($this->canRequest($latest), 409, 'You already have an open proctor request for this exam.');

        $data = $request->validate([
            'proctor_name' => 'required|string|max:120',
            'proctor_email' => ['required', 'email', 'max:190', 'not_in:'.$request->user()->email],
            'proctor_phone' => 'nullable|string|max:40',
            'proctor_relationship' => 'nullable|string|max:120',
            'preferred_date' => 'nullable|date_format:Y-m-d\TH:i',
            'timezone' => 'required|timezone:all',
            'student_notes' => 'nullable|string|max:2000',
            'agree' => 'accepted',
        ], [
            'proctor_email.not_in' => 'Your proctor must be someone else, with their own email address.',
            'agree.accepted' => 'Please confirm your proctor isn’t a relative and will supervise the whole exam.',
        ]);

        $preferred = null;
        if (! empty($data['preferred_date'])) {
            $preferred = Carbon::createFromFormat('Y-m-d\TH:i', $data['preferred_date'], $data['timezone'])->utc();
            if ($preferred->isPast()) throw ValidationException::withMessages(['preferred_date' => 'Choose a time in the future.']);
        }

        $this->proctoring->submit($enrollment, $quiz, [
            'proctor_name' => $data['proctor_name'], 'proctor_email' => strtolower($data['proctor_email']),
            'proctor_phone' => $data['proctor_phone'] ?? null, 'proctor_relationship' => $data['proctor_relationship'] ?? null,
            'preferred_at' => $preferred, 'timezone' => $data['timezone'], 'student_notes' => $data['student_notes'] ?? null,
        ]);

        return redirect()->route('proctor-requests.create', [$course, $lesson])
            ->with('status', 'Request sent. We’ll email you when it’s reviewed, usually within one business day.');
    }

    public function cancel(Request $request, Course $course, Lesson $lesson, ProctorRequest $proctorRequest)
    {
        [$enrollment] = $this->context($request, $course, $lesson);
        abort_unless($proctorRequest->enrollment_id === $enrollment->id && $proctorRequest->isPending(), 404);
        $proctorRequest->update(['status' => 'cancelled']);
        return back()->with('status', 'Request cancelled.');
    }

    /** A new request is allowed unless one is waiting for review or an approved code is still unused. */
    private function canRequest(?ProctorRequest $latest): bool
    {
        if (! $latest) return true;
        if ($latest->isPending()) return false;
        if ($latest->status === 'approved' && $latest->code?->isActive()) return false;
        return true;
    }

    /** @return array{0: Enrollment, 1: \App\Models\Quiz} */
    private function context(Request $request, Course $course, Lesson $lesson): array
    {
        abort_unless($lesson->course_id === $course->id && $lesson->quiz && $lesson->quiz->access_mode === 'code', 404);
        $enrollment = $request->user()->enrollmentFor($course);
        abort_unless($enrollment, 403);
        return [$enrollment, $lesson->quiz];
    }
}
