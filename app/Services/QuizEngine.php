<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;

class QuizEngine
{
    public function __construct(private Progress $progress) {}

    public function attemptsUsed(Quiz $quiz, Enrollment $enrollment): int
    {
        return $quiz->attempts()->where('enrollment_id', $enrollment->id)->whereNotNull('submitted_at')->count();
    }

    public function canAttempt(Quiz $quiz, Enrollment $enrollment): bool
    {
        if ($quiz->questions()->count() === 0) return false;
        if ($quiz->max_attempts === null) return true;
        return $this->attemptsUsed($quiz, $enrollment) < $quiz->max_attempts;
    }

    /** Returns the open attempt if one exists, otherwise starts a new one. */
    public function start(Quiz $quiz, Enrollment $enrollment): QuizAttempt
    {
        $open = $quiz->attempts()->where('enrollment_id', $enrollment->id)->whereNull('submitted_at')->latest()->first();
        if ($open && (! $open->expires_at || $open->expires_at->isFuture())) return $open;
        if ($open) $this->submit($open, $open->answers ?? []); // expired, close it out

        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'question_order' => $this->drawQuestions($quiz, $enrollment),
            'started_at' => now(),
            'expires_at' => $quiz->time_limit_minutes ? now()->addMinutes($quiz->time_limit_minutes) : null,
        ]);
    }

    /**
     * Picks the question ids for a new attempt. With a draw size smaller than the bank,
     * each topic gets a share proportional to its size (largest remainder), and questions
     * this student saw in earlier attempts are only used once the unseen ones run out.
     */
    public function drawQuestions(Quiz $quiz, Enrollment $enrollment): array
    {
        $bank = $quiz->questions()->get(['id', 'topic', 'position']);
        $size = $quiz->attemptSize();

        if ($size >= $bank->count()) {
            $ids = $bank->pluck('id')->all();
            if ($quiz->shuffle_questions) shuffle($ids);
            return $ids;
        }

        $seen = $quiz->attempts()->where('enrollment_id', $enrollment->id)->pluck('question_order')
            ->flatten()->flip()->all();
        $groups = $bank->groupBy(fn ($q) => (string) $q->topic);

        $quotas = []; $remainders = [];
        foreach ($groups as $topic => $questions) {
            $exact = $size * $questions->count() / $bank->count();
            $quotas[$topic] = (int) floor($exact);
            $remainders[$topic] = $exact - floor($exact);
        }
        $topics = array_keys($remainders);
        shuffle($topics); // random tie-break, then largest remainder first (usort is stable)
        usort($topics, fn ($a, $b) => $remainders[$b] <=> $remainders[$a]);
        foreach (array_slice($topics, 0, $size - array_sum($quotas)) as $topic) $quotas[$topic]++;

        $picked = [];
        foreach ($groups as $topic => $questions) {
            $ids = $questions->pluck('id')->shuffle()->sortBy(fn ($id) => isset($seen[$id]) ? 1 : 0);
            array_push($picked, ...$ids->take($quotas[$topic])->all());
        }

        if ($quiz->shuffle_questions) {
            shuffle($picked);
            return $picked;
        }
        $keep = array_flip($picked);
        return $bank->pluck('id')->filter(fn ($id) => isset($keep[$id]))->values()->all();
    }

    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->isSubmitted()) return $attempt;

        $quiz = $attempt->quiz()->with('questions.options', 'lesson')->first();
        $questions = $quiz->questions->keyBy('id');
        $earned = 0; $total = 0; $results = [];

        foreach ($attempt->question_order as $qid) {
            $q = $questions->get($qid);
            if (! $q) continue;
            $total += $q->points;
            $answer = $answers[$qid] ?? null;
            $ok = $q->grade($answer);
            if ($ok) $earned += $q->points;
            $results[$qid] = $ok;
        }

        $score = $total ? (int) round($earned / $total * 100) : 0;
        $attempt->fill([
            'answers' => $answers,
            'results' => $results,
            'points_earned' => $earned,
            'points_total' => $total,
            'score' => $score,
            'passed' => $score >= $quiz->passing_score,
            'submitted_at' => now(),
        ])->save();

        if ($attempt->passed) {
            $this->progress->complete($attempt->enrollment, $quiz->lesson);
        }

        return $attempt;
    }
}
