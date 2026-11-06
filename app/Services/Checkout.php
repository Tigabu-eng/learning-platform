<?php

namespace App\Services;

use App\Mail\OrderReceipt;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\SafeMail;
use Illuminate\Support\Str;

class Checkout
{
    public function __construct(private Progress $progress) {}

    public function findCoupon(?string $code, Course $course): ?Coupon
    {
        if (! $code) return null;
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        return $coupon && $coupon->isUsableFor($course) ? $coupon : null;
    }

    public function quote(Course $course, ?Coupon $coupon): array
    {
        $amount = (float) $course->price;
        $discount = $coupon ? $coupon->discountFor($amount) : 0.0;
        return ['amount' => $amount, 'discount' => $discount, 'total' => round($amount - $discount, 2)];
    }

    public function createOrder(User $user, Course $course, ?Coupon $coupon, string $gateway): Order
    {
        $q = $this->quote($course, $coupon);
        return Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'coupon_id' => $coupon?->id,
            'reference' => 'TARC-'.strtoupper(Str::random(10)),
            'amount' => $q['amount'],
            'discount' => $q['discount'],
            'total' => $q['total'],
            'currency' => config('school.currency'),
            'gateway' => $q['total'] > 0 ? $gateway : 'free',
            'status' => 'pending',
        ]);
    }

    /** Marks an order paid exactly once and enrolls the student. */
    public function fulfil(Order $order): void
    {
        $justPaid = false;
        DB::transaction(function () use ($order, &$justPaid) {
            $order = Order::lockForUpdate()->find($order->id);
            if ($order->isPaid()) return;
            $justPaid = true;
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            if ($order->coupon_id) Coupon::whereKey($order->coupon_id)->increment('uses');
            $this->progress->enroll($order->user, $order->course, $order->id);
        });

        // Once only: a webhook and the return page can both report the same payment.
        if ($justPaid) {
            $order = $order->fresh(['user', 'course', 'coupon']);
            SafeMail::send($order->user->email, new OrderReceipt($order));
        }
    }
}
