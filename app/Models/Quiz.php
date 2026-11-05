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

    /** How many questions one attempt gets: the draw size, capped at the size of the bank. */
    public function attemptSize(): int
    {
        $bank = $this->questions()->count();
        return $this->questions_per_attempt ? min($this->questions_per_attempt, $bank) : $bank;
    }

    /** Question counts per topic, untagged questions under "". */
    public function topicCounts(): array
    {
        return $this->questions()->reorder()->toBase()->selectRaw("coalesce(topic, '') as t, count(*) as n")
            ->groupBy('t')->orderBy('t')->pluck('n', 't')->map(fn ($n) => (int) $n)->all();
    }
}
