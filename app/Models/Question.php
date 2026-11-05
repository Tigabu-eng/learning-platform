<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = ['id'];

    public const TYPES = [
        'single' => 'Multiple choice (one answer)',
        'multiple' => 'Multiple choice (several answers)',
        'true_false' => 'True / false',
        'short' => 'Short answer',
    ];

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function options() { return $this->hasMany(QuestionOption::class)->orderBy('position'); }

    /** Editor HTML is sanitized before it is stored. */
    public function setPromptAttribute(?string $value): void { $this->attributes['prompt'] = \App\Support\RichText::clean($value) ?? ''; }
    public function setExplanationAttribute(?string $value): void { $this->attributes['explanation'] = \App\Support\RichText::clean($value); }

    public function acceptedAnswers(): array
    {
        return collect(preg_split('/\r?\n/', (string) $this->accepted_answers))
            ->map(fn ($a) => self::normalize($a))->filter()->values()->all();
    }

    public static function normalize(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $s)));
    }

    /** Grades a raw answer. Returns true when fully correct. */
    public function grade(mixed $answer): bool
    {
        $correct = $this->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return match ($this->type) {
            'single', 'true_false' => is_scalar($answer) && in_array((int) $answer, $correct, true) && count($correct) >= 1,
            'multiple' => (function () use ($answer, $correct) {
                $given = collect((array) $answer)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
                return $given === $correct && $correct !== [];
            })(),
            'short' => is_string($answer) && in_array(self::normalize($answer), $this->acceptedAnswers(), true),
            default => false,
        };
    }
}
