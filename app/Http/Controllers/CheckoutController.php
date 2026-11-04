<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Order;
use App\Services\Checkout;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\Request;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(private Checkout $checkout) {}

    public function show(Request $request, Course $course)
    {
        abort_unless($course->isPublished(), 404);
        if ($request->user()->enrollmentFor($course)) return redirect()->route('learn.resume', $course);

        $coupon = $this->checkout->findCoupon($request->coupon, $course);
        $couponError = $request->filled('coupon') && ! $coupon ? 'That code isn’t valid for this course.' : null;
        $quote = $this->checkout->quote($course, $coupon);
        $gateway = config('school.gateway');

        return view('checkout.show', compact('course', 'coupon', 'couponError', 'quote', 'gateway'));
    }

    public function pay(Request $request, Course $course)
    {
        abort_unless($course->isPublished(), 404);
        $user = $request->user();
        if ($user->enrollmentFor($course)) return redirect()->route('learn.resume', $course);

        $coupon = $this->checkout->findCoupon($request->coupon, $course);
        $order = $this->checkout->createOrder($user, $course, $coupon, config('school.gateway'));

        if ((float) $order->total <= 0) {
            $this->checkout->fulfil($order);
            return redirect()->route('learn.resume', $course)->with('status', 'You’re enrolled. Welcome to the course!');
        }

        try {
            return redirect()->away(GatewayManager::make($order->gateway)->start($order));
        } catch (Throwable $e) {
            $order->update(['status' => 'failed', 'meta' => ['error' => $e->getMessage()]]);
            return back()->withErrors(['payment' => 'Payment couldn’t start: '.$e->getMessage()]);
        }
    }

    public function sandbox(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id && $order->gateway === 'test', 404);
        return view('checkout.sandbox', compact('order'));
    }

    public function sandboxDecide(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id && $order->gateway === 'test', 404);
        $result = $request->input('decision') === 'approve' ? 'approved' : 'declined';
        $order->update(['meta' => array_merge($order->meta ?? [], ['sandbox_result' => $result])]);
        return redirect()->route('checkout.return', $order);
    }

    public function return(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if (! $order->isPaid()) {
            try {
                $paid = GatewayManager::make($order->gateway)->verify($order);
            } catch (Throwable $e) {
                $paid = false;
            }
            if (! $paid) {
                $order->update(['status' => 'failed']);
                return redirect()->route('checkout.show', $order->course)
                    ->withErrors(['payment' => 'The payment wasn’t completed. You haven’t been charged — try again when you’re ready.']);
            }
            $this->checkout->fulfil($order);
        }

        return redirect()->route('learn.resume', $order->course)->with('status', 'Payment received. You’re enrolled!');
    }

    /** Server-to-server notification (Chapa sends tx_ref / trx_ref). Always re-verifies with the provider. */
    public function webhook(Request $request, string $gateway)
    {
        $ref = $request->input('tx_ref') ?? $request->input('trx_ref') ?? $request->input('reference');
        $order = $ref ? Order::where('reference', $ref)->where('gateway', $gateway)->first() : null;
        if ($order && ! $order->isPaid() && GatewayManager::make($gateway)->verify($order)) {
            $this->checkout->fulfil($order);
        }
        return response()->json(['ok' => true]);
    }
}
