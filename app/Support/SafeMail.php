<?php

namespace App\Support;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an email without letting a mail-server problem break the page the person is on.
 * Failures are reported to the log; the caller gets false.
 */
class SafeMail
{
    public static function send(string $to, Mailable $mail): bool
    {
        try {
            Mail::to($to)->send($mail);
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
