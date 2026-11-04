<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Stripe Checkout via the REST API (no SDK needed). */
class StripeGateway implements Gateway
{
    private function http()
    {
        $key = config('school.gateways.stripe.secret_key');
        if (! $key) throw new RuntimeException('Set STRIPE_SECRET_KEY in .env to use Stripe.');
        return Http::withToken($key)->asForm()->baseUrl('https://api.stripe.com/v1');
    }

    public function start(Order $order): string
    {
        $res = $this->http()->post('/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $order->reference,
            'customer_email' => $order->user->email,
            'success_url' => route('checkout.return', $order),
            'cancel_url' => route('checkout.show', $order->course),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => (int) round($order->total * 100),
                    'product_data' => ['name' => $order->course->title],
                ],
            ]],
        ]);
        if (! $res->successful()) throw new RuntimeException('Stripe: '.($res->json('error.message') ?? 'could not start payment'));
        $order->update(['meta' => array_merge($order->meta ?? [], ['stripe_session' => $res->json('id')])]);
        return $res->json('url');
    }

    public function verify(Order $order): bool
    {
        $id = $order->meta['stripe_session'] ?? null;
        if (! $id) return false;
        $res = $this->http()->get('/checkout/sessions/'.$id);
        return $res->successful() && $res->json('payment_status') === 'paid';
    }
}
