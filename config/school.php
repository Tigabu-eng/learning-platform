<?php

return [
    'currency' => env('SCHOOL_CURRENCY', 'USD'),

    // Which payment gateway handles paid checkouts: test | chapa | stripe
    'gateway' => env('PAYMENT_GATEWAY', 'test'),

    'gateways' => [
        'chapa' => [
            'secret_key' => env('CHAPA_SECRET_KEY'),
            'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
        ],
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
        ],
    ],
];
