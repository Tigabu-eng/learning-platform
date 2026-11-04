<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'question_order' => 'array',
        'answers' => 'array',
        'results' => 'array',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }

    public function isSubmitted(): bool { return $this->submitted_at !== null; }

    public function secondsLeft(): ?int
    {
        return $this->expires_at ? max(0, now()->diffInSeconds($this->expires_at, false)) : null;
    }
}
