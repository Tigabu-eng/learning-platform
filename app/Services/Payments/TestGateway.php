<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Built-in sandbox: shows a page where you approve or decline the
 * payment. No money moves. Use it while building and switch to Chapa/Stripe later.
 */
class TestGateway implements Gateway
{
    public function start(Order $order): string
    {
        return route('checkout.sandbox', $order);
    }

    public function verify(Order $order): bool
    {
        return ($order->meta['sandbox_result'] ?? null) === 'approved';
    }
}
