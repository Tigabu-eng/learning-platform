<?php

namespace App\Mail;

use App\Models\ProctorRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To TARC staff: a student asked for a proctored exam. */
class NewProctorRequest extends Mailable
{
    public function __construct(public ProctorRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New proctor request: '.$this->request->enrollment->user->name.' – '.$this->request->quiz->lesson->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-proctor-request', with: [
            'r' => $this->request,
            'student' => $this->request->enrollment->user,
            'exam' => $this->request->quiz->lesson,
            'course' => $this->request->quiz->lesson->course,
            'link' => route('studio.proctoring', ['status' => 'pending']),
        ]);
    }
}
