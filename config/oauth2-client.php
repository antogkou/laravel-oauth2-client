<?php

declare(strict_types=1);

return [
    'services' => [
        'default' => [
            'client_id' => env('OAUTH2_CLIENT_ID'),
            'client_secret' => env('OAUTH2_CLIENT_SECRET'),
            'token_url' => env('OAUTH2_TOKEN_URL'),
            'scope' => env('OAUTH2_SCOPE', ''),
            'base_uri' => env('OAUTH2_BASE_URI'),
        ],
    ],
    'cache_prefix' => 'oauth2_',
    'expiration_buffer' => 60,
    'logging' => [
        'enabled' => env('OAUTH2_CLIENT_LOGGING', true),
        'level' => 'error',
        'redact' => [
            'headers.authorization',
            'body.password',
            'response.access_token',
        ],
    ],
];
