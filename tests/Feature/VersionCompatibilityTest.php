<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Feature;

use Antogkou\LaravelOAuth2Client\Facades\OAuth2;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;

test('package works with current PHP and Laravel version', function (): void {
    // Get current PHP version
    $phpVersion = PHP_VERSION;
    $laravelVersion = Application::VERSION;

    // Log versions for debugging in CI
    info("Testing with PHP {$phpVersion} and Laravel {$laravelVersion}");

    // Basic functionality test
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data' => Http::response(['success' => true], 200),
    ]);

    $response = OAuth2::for('test_service')->get('https://api.example.com/data');

    expect($response->status())->toBe(200);
});
