<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the student when an order is paid (or a free enrollment completes). */
class OrderReceipt extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ((float) $this->order->total > 0 ? 'Your receipt: ' : 'You’re enrolled: ').$this->order->course->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-receipt', with: [
            'order' => $this->order,
            'course' => $this->order->course,
            'user' => $this->order->user,
        ]);
    }
}
