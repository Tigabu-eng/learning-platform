<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Enrollment, lesson access rules (drip, sequential, final-exam gating),
 * completion tracking and certificate issuing.
 */
class Progress
{
    public function enroll(User $user, Course $course, ?int $orderId = null): Enrollment
    {
        $enrollment = Enrollment::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'order_id' => $orderId,
                'enrolled_at' => now(),
                'expires_at' => $course->access_days ? now()->addDays($course->access_days) : null,
            ]
        );
        if ($orderId && ! $enrollment->order_id) $enrollment->update(['order_id' => $orderId]);
        return $enrollment;
    }

    /**
     * Returns null when the lesson is open, or a human-readable reason it is locked.
     */
    public function lockReason(Lesson $lesson, ?Enrollment $enrollment, ?Collection $ordered = null, ?array $done = null): ?string
    {
        $course = $lesson->course;
        if (! $enrollment) {
            return $lesson->is_preview ? null : 'Enroll to unlock this lesson.';
        }
        if ($enrollment->isExpired()) return 'Your access to this course has ended.';

        if ($course->drip_enabled && $lesson->drip_days) {
            $opens = $enrollment->enrolled_at->copy()->addDays($lesson->drip_days);
            if ($opens->isFuture()) return 'Available on '.$opens->format('M j, Y').'.';
        }

        $ordered ??= $course->orderedLessons();
        $done ??= $enrollment->completedLessonIds();

        if ($course->sequential) {
            foreach ($ordered as $l) {
                if ($l->id === $lesson->id) break;
                if (! in_array($l->id, $done, true)) return 'Complete "'.$l->title.'" first.';
            }
        }

        if ($lesson->type === 'exam' && $lesson->quiz?->require_all_lessons) {
            $remaining = $ordered->filter(fn ($l) => $l->id !== $lesson->id && $l->type !== 'exam' && ! in_array($l->id, $done, true));
            if ($remaining->isNotEmpty()) return 'Finish all lessons to unlock the final exam ('.$remaining->count().' left).';
        }

        return null;
    }

    public function complete(Enrollment $enrollment, Lesson $lesson): void
    {
        LessonCompletion::firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );
        $this->recalculate($enrollment);
    }

    public function recalculate(Enrollment $enrollment): void
    {
        $course = $enrollment->course;
        $total = $course->lessons()->count();
        $done = $enrollment->completions()->whereIn('lesson_id', $course->lessons()->pluck('id'))->count();
        $percent = $total ? (int) floor($done / $total * 100) : 0;

        $enrollment->progress_percent = $percent;
        if ($percent === 100 && ! $enrollment->completed_at) {
            $enrollment->completed_at = now();
        }
        $enrollment->save();

        if ($percent === 100) $this->issueCertificate($enrollment);
    }

    public function issueCertificate(Enrollment $enrollment): ?Certificate
    {
        $course = $enrollment->course;
        if (! $course->certificate_enabled) return null;
        if ($existing = $enrollment->certificate) return $existing;

        $final = $course->finalExam();
        $score = null;
        if ($final) {
            $passed = $enrollment->attempts()->where('quiz_id', $final->id)->where('passed', true)->exists();
            if (! $passed) return null;
            $score = $enrollment->bestScoreFor($final);
        }

        do { $code = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4)); }
        while (Certificate::where('code', $code)->exists());

        return Certificate::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $course->id,
            'code' => $code,
            'final_score' => $score,
            'issued_at' => now(),
        ]);
    }
}
