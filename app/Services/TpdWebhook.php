<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TpdWebhook
{
    public function send(string $event, array $payload): void
    {
        $url = trim((string) config('tpd.webhook_url'));
        if ($url === '') {
            return;
        }

        $body = json_encode([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ], JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            return;
        }

        $secret = (string) config('tpd.webhook_secret');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-LMS-Event' => $event,
        ];

        if ($secret !== '') {
            $headers['X-LMS-Signature'] = hash_hmac('sha256', $body, $secret);
        }

        try {
            Http::timeout((int) config('tpd.webhook_timeout', 4))
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable $e) {
            Log::warning('TPD webhook delivery failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
