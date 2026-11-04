<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'sequential' => 'boolean',
        'drip_enabled' => 'boolean',
        'certificate_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (! $course->slug) {
                $base = Str::slug($course->title) ?: 'course';
                $slug = $base; $i = 2;
                while (static::where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
                $course->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function instructor() { return $this->belongsTo(User::class, 'instructor_id'); }
    public function chapters() { return $this->hasMany(Chapter::class)->orderBy('position'); }
    public function lessons() { return $this->hasMany(Lesson::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function coupons() { return $this->hasMany(Coupon::class); }
    public function certificates() { return $this->hasMany(Certificate::class); }

    /** Lessons in curriculum order (chapter position, then lesson position). */
    public function orderedLessons()
    {
        return $this->lessons()
            ->join('chapters', 'chapters.id', '=', 'lessons.chapter_id')
            ->orderBy('chapters.position')->orderBy('lessons.position')
            ->select('lessons.*')->get();
    }

    public function isFree(): bool { return (float) $this->price <= 0; }
    public function isPublished(): bool { return $this->status === 'published'; }

    public function canBeManagedBy(?User $user): bool
    {
        return $user && ($user->isAdmin() || $user->id === $this->instructor_id);
    }

    public function finalExam(): ?Quiz
    {
        return Quiz::where('is_final', true)
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $this->id))->first();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }

    public function totalMinutes(): int { return (int) $this->lessons()->sum('duration_minutes'); }

    public function priceLabel(): string
    {
        return $this->isFree() ? 'Free' : money($this->price);
    }
}
