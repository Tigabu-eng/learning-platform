<?php

namespace App\Http\Controllers;

use App\Models\ProctorRequest;
use Illuminate\Http\Request;

/** The proctor's private page, reached from the link in their email. No account needed. */
class ProctorPortalController extends Controller
{
    public function show(string $token)
    {
        $r = $this->find($token);
        return response()->view('proctor.show', ['r' => $r, 'token' => $token])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function confirm(Request $request, string $token)
    {
        $r = $this->find($token);
        abort_unless($r->code?->used_at, 409, 'You can confirm the session once the exam has started.');
        $data = $request->validate([
            'supervised' => 'accepted',
            'flagged' => 'nullable|boolean',
            'report' => 'nullable|string|max:5000|required_if:flagged,1',
        ], [
            'supervised.accepted' => 'Please confirm you supervised the whole exam.',
            'report.required_if' => 'Please describe what happened.',
        ]);
        $r->update(['proctor_confirmed_at' => now(), 'proctor_flagged' => $request->boolean('flagged'), 'proctor_report' => $data['report'] ?? null]);
        return redirect()->route('proctor.show', $token)->with('status', 'Thank you. Your confirmation has been sent to TARC.');
    }

    private function find(string $token): ProctorRequest
    {
        $r = ProctorRequest::findByToken($token);
        abort_unless($r && $r->status === 'approved', 404);
        return $r->load(['enrollment.user', 'quiz.lesson.course', 'code.attempt']);
    }
}
