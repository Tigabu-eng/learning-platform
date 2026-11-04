<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Chapa (Ethiopia) — supports Telebirr, CBE Birr, M-Pesa, cards. */
class ChapaGateway implements Gateway
{
    private function http()
    {
        $key = config('school.gateways.chapa.secret_key');
        if (! $key) throw new RuntimeException('Set CHAPA_SECRET_KEY in .env to use Chapa.');
        return Http::withToken($key)->baseUrl(config('school.gateways.chapa.base_url'))->acceptJson();
    }

    public function start(Order $order): string
    {
        [$first, $last] = array_pad(explode(' ', $order->user->name, 2), 2, '-');
        $res = $this->http()->post('/transaction/initialize', [
            'amount' => (string) $order->total,
            'currency' => $order->currency,
            'email' => $order->user->email,
            'first_name' => $first,
            'last_name' => $last,
            'tx_ref' => $order->reference,
            'callback_url' => route('checkout.webhook', ['gateway' => 'chapa']),
            'return_url' => route('checkout.return', $order),
            'customization' => ['title' => config('app.name'), 'description' => 'Course enrollment'],
        ]);
        $url = $res->json('data.checkout_url');
        if (! $res->successful() || ! $url) throw new RuntimeException('Chapa: '.($res->json('message') ?? 'could not start payment'));
        return $url;
    }

    public function verify(Order $order): bool
    {
        $res = $this->http()->get('/transaction/verify/'.$order->reference);
        return $res->successful() && $res->json('data.status') === 'success'
            && (float) $res->json('data.amount') >= (float) $order->total;
    }
}
