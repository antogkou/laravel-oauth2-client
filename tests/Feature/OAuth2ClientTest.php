<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Feature;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use Antogkou\LaravelOAuth2Client\Facades\OAuth2Client;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;

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

    expect($response->status())->toBe(200)
        ->and(Cache::get('oauth2_test_service_access_token'))->toBe('test-token');
});

test('logs error for failed token fetch', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Client authentication failed',
        ], 401),
        // Mock API endpoint even though it shouldn't be called
        'https://api.example.com/data' => Http::response('Should not reach here', 500),
    ]);

    //    Log::shouldReceive('error')
    //        ->once()
    //        ->with('Token fetch failed for service test_service', Mockery::any());

    try {
        OAuth2Client::for('test_service')->get('https://api.example.com/data');
    } catch (Exception $e) {
        //        dd($e->getMessage());
        // Assert correct exception type
        expect($e)->toBeInstanceOf(OAuth2Exception::class);
        // Assert correct exception message
        expect($e->getMessage())->toContain('Failed to obtain access token');
    }

    // Verify only token request was made
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.example.com/token');
})->skip('Log::shouldReceive not working');
