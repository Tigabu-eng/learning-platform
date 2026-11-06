<?php

namespace App\Mail;

use App\Models\ProctorRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the proctor: the exam session details, the one-time code and their private link. */
class ProctorAssigned extends Mailable
{
    public function __construct(public ProctorRequest $request, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Proctor details: '.$this->request->enrollment->user->name.' – '.$this->request->quiz->lesson->title,
            replyTo: [config('school.brand.support_email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.proctor-assigned', with: [
            'r' => $this->request,
            'student' => $this->request->enrollment->user,
            'exam' => $this->request->quiz->lesson,
            'course' => $this->request->quiz->lesson->course,
            'code' => $this->request->code,
            'link' => route('proctor.show', $this->token),
        ]);
    }
}
