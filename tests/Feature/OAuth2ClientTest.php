<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Feature;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use Antogkou\LaravelOAuth2Client\Facades\OAuth2Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config([
        'oauth2-client.services.test_service' => [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'token_url' => 'https://auth.example.com/token',
            'scope' => 'api',
        ],
        'oauth2-client.cache_prefix' => 'oauth2_',
    ]);
});

test('fetches and caches token', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data' => Http::response(['success' => true], 200),
    ]);

    $response = OAuth2Client::for('test_service')->get('https://api.example.com/data');

    expect($response->status())->toBe(200);
    expect(Cache::get('oauth2_test_service_access_token'))->toBe('test-token');
});

test('logs error for failed token fetch', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([], 401),
    ]);

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn($message, array $context): bool => str_contains((string) $message, 'Token fetch failed for service test_service') &&
            $context['status'] === 401);

    expect(fn() => OAuth2Client::for('test_service')->get('https://api.example.com/data'))
        ->toThrow(OAuth2Exception::class);
});
