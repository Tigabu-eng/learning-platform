<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonCompletion extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['completed_at' => 'datetime'];

    public function lesson() { return $this->belongsTo(Lesson::class); }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
}
