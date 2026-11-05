<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A one-time code that lets one student start one attempt of one exam. Given to their proctor. */
class ExamAccessCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'code' => 'encrypted',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    // No 0/O, 1/I/L: easy to read out over a video call.
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function attempt() { return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public static function generate(): string
    {
        $chars = '';
        for ($i = 0; $i < 8; $i++) $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        return substr($chars, 0, 4).'-'.substr($chars, 4);
    }

    /** Uppercase, without spaces or dashes, so "k7qf m2xp" matches "K7QF-M2XP". */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }

    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->used_at !== null => 'used',
            $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    public function isActive(): bool { return $this->status() === 'active'; }
}
