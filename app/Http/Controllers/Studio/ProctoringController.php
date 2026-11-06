<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ProctorRequest;
use App\Services\Proctoring;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The Studio queue of proctor requests: admins see every course, instructors their own. */
class ProctoringController extends Controller
{
    public function __construct(private Proctoring $proctoring) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $status = in_array($request->status, array_keys(ProctorRequest::STATUSES), true) ? $request->status : 'pending';
        $scope = fn ($q) => $user->isAdmin() ? $q : $q->whereIn('quiz_id', $this->quizIds($user));

        $requests = $scope(ProctorRequest::query())
            ->with(['enrollment.user', 'quiz.lesson.course', 'code.attempt', 'decider'])
            ->where('status', $status)
            ->orderBy($status === 'pending' ? 'created_at' : 'decided_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(25)->withQueryString();
        $counts = $scope(ProctorRequest::query())->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('studio.proctoring', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, ProctorRequest $proctorRequest)
    {
        $this->authorizeRequest($request, $proctorRequest);
        abort_unless($proctorRequest->isPending(), 409);
        $data = $request->validate([
            'valid_hours' => ['required', Rule::in(array_keys(Proctoring::VALID_FOR_HOURS))],
            'note' => 'nullable|string|max:2000',
        ]);
        $sent = $this->proctoring->approve($proctorRequest, $request->user(), (int) $data['valid_hours'], $data['note'] ?? null);
        return $this->done($sent, 'Approved. The proctor has the code and instructions, and the student has been told.');
    }

    public function decline(Request $request, ProctorRequest $proctorRequest)
    {
        $this->authorizeRequest($request, $proctorRequest);
        abort_unless($proctorRequest->isPending(), 409);
        $data = $request->validate(['reason' => 'required|string|max:2000'], ['reason.required' => 'Tell the student why, so they can send a new request.']);
        $sent = $this->proctoring->decline($proctorRequest, $request->user(), $data['reason']);
        return $this->done($sent, 'Declined. The student has been told why.');
    }

    public function resend(Request $request, ProctorRequest $proctorRequest)
    {
        $this->authorizeRequest($request, $proctorRequest);
        abort_unless($proctorRequest->status === 'approved', 409);
        $sent = $this->proctoring->resendToProctor($proctorRequest);
        return $this->done($sent, 'Sent the proctor a new link. The old link no longer works.');
    }

    private function done(bool $sent, string $message)
    {
        return $sent
            ? back()->with('status', $message)
            : back()->with('status', $message)->withErrors(['mail' => 'Saved, but an email couldn’t be sent. Check the mail settings, then use “Resend to proctor”, or send the details yourself.']);
    }

    private function authorizeRequest(Request $request, ProctorRequest $proctorRequest): void
    {
        CourseController::authorizeCourse($request, $proctorRequest->quiz->lesson->course);
    }

    private function quizIds($user)
    {
        return \App\Models\Quiz::whereIn('lesson_id', \App\Models\Lesson::whereIn('course_id', Course::where('instructor_id', $user->id)->select('id'))->select('id'))->select('id');
    }
}
