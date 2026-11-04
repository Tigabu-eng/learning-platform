<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_final' => 'boolean',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'show_answers' => 'boolean',
        'require_all_lessons' => 'boolean',
    ];

    public function lesson() { return $this->belongsTo(Lesson::class); }
    public function questions() { return $this->hasMany(Question::class)->orderBy('position'); }
    public function attempts() { return $this->hasMany(QuizAttempt::class); }

    public function totalPoints(): int { return (int) $this->questions()->sum('points'); }
}
