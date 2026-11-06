<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To someone staff enrolled from the Studio. A new account gets a link to choose a password
 * ($setPasswordUrl); an existing one just gets the course link.
 */
class EnrolledByStaff extends Mailable
{
    public function __construct(public User $user, public Course $course, public ?string $setPasswordUrl = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You’re enrolled: '.$this->course->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.enrolled-by-staff', with: [
            'user' => $this->user,
            'course' => $this->course,
            'setPasswordUrl' => $this->setPasswordUrl,
            'days' => (int) round(config('auth.passwords.invites.expire') / 1440),
        ]);
    }
}
