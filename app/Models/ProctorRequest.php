<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A student's request to take a code-locked exam with a proctor they chose.
 * Staff approve it (which creates the one-time code and emails the proctor) or decline it.
 * The proctor gets a private link (no account) to see the code and confirm the session afterwards.
 */
class ProctorRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'preferred_at' => 'datetime',
        'decided_at' => 'datetime',
        'proctor_confirmed_at' => 'datetime',
        'proctor_flagged' => 'boolean',
    ];

    protected $hidden = ['proctor_token_hash'];

    public const STATUSES = ['pending' => 'Waiting for review', 'approved' => 'Approved', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function code() { return $this->belongsTo(ExamAccessCode::class, 'exam_access_code_id'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }

    public function isPending(): bool { return $this->status === 'pending'; }

    /** Makes a new private link for the proctor. Returns the plain token; only its hash is kept. */
    public function issueProctorToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['proctor_token_hash' => hash('sha256', $token)])->save();
        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return strlen($token) === 48 ? self::where('proctor_token_hash', hash('sha256', $token))->first() : null;
    }

    /** The preferred time in the student's own time zone, e.g. "Tue, Nov 10, 2026, 10:00 AM (America/New_York)". */
    public function preferredLabel(): ?string
    {
        if (! $this->preferred_at) return null;
        return $this->preferred_at->copy()->setTimezone($this->tz())->format('D, M j, Y, g:i A').' ('.str_replace('_', ' ', $this->tz()).')';
    }

    /** Any time shown in the student's time zone, e.g. "Nov 9, 2026 3:00 PM EST", so it matches the session time. */
    public function localTime(?\DateTimeInterface $time): ?string
    {
        return $time ? \Illuminate\Support\Carbon::instance($time)->setTimezone($this->tz())->format('M j, Y g:i A T') : null;
    }

    private function tz(): string { return $this->timezone ?: config('app.timezone'); }
}
