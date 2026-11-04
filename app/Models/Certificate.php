<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['issued_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }

    public function getRouteKeyName(): string { return 'code'; }
}
