<?php

return [
    'currency' => env('SCHOOL_CURRENCY', 'USD'),

    // Travel Advisor Resource Center (TARC) links shown in the header, footer and emails.
    'brand' => [
        'short_name' => 'TARC',
        'site_url' => env('TARC_SITE_URL', 'https://traveladvisorresourcecenter.com'),
        'community_url' => env('TARC_COMMUNITY_URL', 'https://traveladvisorresourcecenter.com'),
        'support_email' => env('TARC_SUPPORT_EMAIL', 'admin@thetarc.com'),
    ],

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
