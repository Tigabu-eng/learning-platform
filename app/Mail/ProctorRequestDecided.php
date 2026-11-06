<?php

namespace App\Mail;

use App\Models\ProctorRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the student: their proctor request was approved (no code: the proctor has it) or declined. */
class ProctorRequestDecided extends Mailable
{
    public function __construct(public ProctorRequest $request) {}

    public function envelope(): Envelope
    {
        $exam = $this->request->quiz->lesson->title;
        return new Envelope(
            subject: $this->request->status === 'approved' ? "Your proctored exam is approved: $exam" : "About your proctor request: $exam",
            replyTo: [config('school.brand.support_email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.proctor-request-decided', with: [
            'r' => $this->request,
            'student' => $this->request->enrollment->user,
            'exam' => $this->request->quiz->lesson,
            'course' => $this->request->quiz->lesson->course,
            'link' => route('learn.lesson', [$this->request->quiz->lesson->course, $this->request->quiz->lesson]),
        ]);
    }
}
