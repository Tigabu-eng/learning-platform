<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function coupon() { return $this->belongsTo(Coupon::class); }

    public function isPaid(): bool { return $this->status === 'paid'; }
}
