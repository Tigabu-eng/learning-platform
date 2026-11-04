<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['value' => 'decimal:2', 'active' => 'boolean', 'expires_at' => 'date'];

    public function course() { return $this->belongsTo(Course::class); }

    public function isUsableFor(Course $course): bool
    {
        if (! $this->active) return false;
        if ($this->course_id && $this->course_id !== $course->id) return false;
        if (! $this->course_id && $this->created_by !== $course->instructor_id && ! User::find($this->created_by)?->isAdmin()) return false;
        if ($this->expires_at && $this->expires_at->endOfDay()->isPast()) return false;
        if ($this->max_uses !== null && $this->uses >= $this->max_uses) return false;
        return true;
    }

    public function discountFor(float $amount): float
    {
        $d = $this->type === 'percent' ? $amount * ((float) $this->value / 100) : (float) $this->value;
        return round(min($amount, max(0, $d)), 2);
    }

    public function label(): string
    {
        return $this->type === 'percent' ? rtrim(rtrim((string) $this->value, '0'), '.').'% off' : money($this->value).' off';
    }
}
