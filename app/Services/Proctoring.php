<?php

namespace App\Services;

use App\Mail\NewProctorRequest;
use App\Mail\ProctorAssigned;
use App\Mail\ProctorRequestDecided;
use App\Models\Enrollment;
use App\Models\ExamAccessCode;
use App\Models\ProctorRequest;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Proctored exams: student requests, staff decisions, one-time codes and the emails that go with them. */
class Proctoring
{
    public const VALID_FOR_HOURS = [24 => '24 hours', 72 => '3 days', 168 => '1 week', 336 => '2 weeks'];

    public function submit(Enrollment $enrollment, Quiz $quiz, array $data): ProctorRequest
    {
        $request = ProctorRequest::create($data + ['quiz_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'status' => 'pending']);
        $recipients = collect([config('school.brand.support_email'), $quiz->lesson->course->instructor?->email])->filter()->unique()->values()->all();
        if ($recipients) $this->send(fn () => Mail::to($recipients)->send(new NewProctorRequest($request)));
        return $request;
    }

    /** Approves a request: creates the one-time code and emails the proctor and the student. Returns whether every email went out. */
    public function approve(ProctorRequest $request, User $staff, int $validHours, ?string $note): bool
    {
        $token = DB::transaction(function () use ($request, $staff, $validHours, $note) {
            $code = ExamAccessCode::create([
                'quiz_id' => $request->quiz_id,
                'enrollment_id' => $request->enrollment_id,
                'code' => ExamAccessCode::generate(),
                'proctor_name' => $request->proctor_name,
                'proctor_email' => $request->proctor_email,
                'notes' => $note,
                'expires_at' => $this->expiry($request, $validHours),
                'created_by' => $staff->id,
            ]);
            $request->update([
                'status' => 'approved', 'decision_note' => $note, 'decided_by' => $staff->id, 'decided_at' => now(),
                'exam_access_code_id' => $code->id,
            ]);
            return $request->issueProctorToken();
        });
        return $this->emailApproval($request->fresh(), $token);
    }

    public function decline(ProctorRequest $request, User $staff, string $reason): bool
    {
        $request->update(['status' => 'declined', 'decision_note' => $reason, 'decided_by' => $staff->id, 'decided_at' => now()]);
        return $this->send(fn () => Mail::to($request->enrollment->user->email)->send(new ProctorRequestDecided($request)));
    }

    /** Staff generated a code by hand for a proctor: record it as an approved request so the proctor gets the same email and link. */
    public function recordManualCode(ExamAccessCode $code, User $staff): bool
    {
        $request = ProctorRequest::create([
            'quiz_id' => $code->quiz_id, 'enrollment_id' => $code->enrollment_id,
            'proctor_name' => $code->proctor_name ?: 'Proctor', 'proctor_email' => $code->proctor_email,
            'status' => 'approved', 'decision_note' => $code->notes, 'decided_by' => $staff->id, 'decided_at' => now(),
            'exam_access_code_id' => $code->id,
        ]);
        return $this->emailApproval($request, $request->issueProctorToken());
    }

    /** New link and a fresh copy of the email, e.g. if the proctor lost it. The old link stops working. */
    public function resendToProctor(ProctorRequest $request): bool
    {
        $token = $request->issueProctorToken();
        return $this->send(fn () => Mail::to($request->proctor_email)->send(new ProctorAssigned($request, $token)));
    }

    private function emailApproval(ProctorRequest $request, string $token): bool
    {
        $proctor = $this->send(fn () => Mail::to($request->proctor_email)->send(new ProctorAssigned($request, $token)));
        $student = $this->send(fn () => Mail::to($request->enrollment->user->email)->send(new ProctorRequestDecided($request)));
        return $proctor && $student;
    }

    /** The code lasts the chosen time, and at least until a day after the preferred session. */
    private function expiry(ProctorRequest $request, int $validHours)
    {
        $expires = now()->addHours($validHours);
        if ($request->preferred_at && $request->preferred_at->copy()->addDay()->gt($expires)) {
            $expires = $request->preferred_at->copy()->addDay();
        }
        return $expires;
    }

    /** Sends without letting a mail-server problem break the page; the failure is logged. */
    private function send(callable $send): bool
    {
        try {
            $send();
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
