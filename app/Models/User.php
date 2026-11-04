<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'bio'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isInstructor(): bool { return $this->role === 'instructor'; }
    public function canTeach(): bool { return in_array($this->role, ['admin', 'instructor'], true); }

    public function courses() { return $this->hasMany(Course::class, 'instructor_id'); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function certificates() { return $this->hasMany(Certificate::class); }

    public function enrollmentFor(Course $course): ?Enrollment
    {
        return $this->enrollments()->where('course_id', $course->id)->first();
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
