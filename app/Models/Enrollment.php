<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function completions() { return $this->hasMany(LessonCompletion::class); }
    public function attempts() { return $this->hasMany(QuizAttempt::class); }
    public function certificate() { return $this->hasOne(Certificate::class); }
    public function lastLesson() { return $this->belongsTo(Lesson::class, 'last_lesson_id'); }

    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }

    public function completedLessonIds(): array
    {
        return $this->completions()->pluck('lesson_id')->map(fn ($id) => (int) $id)->all();
    }

    public function bestScoreFor(Quiz $quiz): ?int
    {
        $s = $this->attempts()->where('quiz_id', $quiz->id)->whereNotNull('submitted_at')->max('score');
        return $s === null ? null : (int) $s;
    }
}
