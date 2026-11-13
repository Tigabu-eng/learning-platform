<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Secrets & API Authentication
    |--------------------------------------------------------------------------
    | Configure both values identically on WordPress (tpd-tool) and Laravel.
    | Keeping domains and secrets env-driven makes future domain changes easy.
    */
    'sso_secret' => env('TPD_SSO_SECRET'),
    'api_token' => env('TPD_API_TOKEN'),

    // Maximum lifetime (seconds) for a signed WordPress -> LMS SSO redirect token.
    'sso_ttl' => max(30, (int) env('TPD_SSO_TTL', 120)),

    /*
    |--------------------------------------------------------------------------
    | WordPress (TPD) Origin & Iframe Embedding
    |--------------------------------------------------------------------------
    | Update TPD_BASE_URL and TPD_FRAME_ANCESTORS in .env if the site domain
    | changes before launch.
    */
    'tpd_base_url' => rtrim((string) env('TPD_BASE_URL', 'https://tpd.tarctpc.com'), '/'),
    'frame_ancestors' => trim((string) env('TPD_FRAME_ANCESTORS', env('TPD_ALLOWED_FRAME_ANCESTORS', "'self' https://tpd.tarctpc.com http://localhost:* http://127.0.0.1:*"))),

    /*
    |--------------------------------------------------------------------------
    | Optional Outbound Webhooks (LMS -> TPD)
    |--------------------------------------------------------------------------
    | When TPD_WEBHOOK_URL is set, LMS notifies TPD of direct course orders,
    | completions, and issued certificates signed with HMAC-SHA256.
    */
    'webhook_url' => env('TPD_WEBHOOK_URL'),
    'webhook_secret' => env('TPD_WEBHOOK_SECRET', env('TPD_SSO_SECRET')),
    'webhook_timeout' => (int) env('TPD_WEBHOOK_TIMEOUT', 4),

    /*
    |--------------------------------------------------------------------------
    | Role Mapping (WordPress TPD Role -> LMS Role)
    |--------------------------------------------------------------------------
    */
    'role_map' => [
        'super_admin' => 'super_admin',
        'administrator' => 'super_admin',
        'admin' => 'admin',
        'instructor' => 'instructor',
        'travel_advisor' => 'student',
        'advisor' => 'student',
        'pro' => 'student',
        'supplier' => 'student',
        'student' => 'student',
    ],
];
