<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Feature;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use Antogkou\LaravelOAuth2Client\Facades\OAuth2;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Clear the cache to ensure we're starting with a clean state for each test
    Cache::flush();

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

    $response = OAuth2::for('test_service')->get('https://api.example.com/data');

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
        OAuth2::for('test_service')->get('https://api.example.com/data');
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

test('disables SSL verification when verify is false', function (): void {

    // Configure a service with verify set to false
    config([
        'oauth2-client.services.no_verify_service' => [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'token_url' => 'https://auth.example.com/token',
            'scope' => 'api',
            'verify' => false,
        ],
    ]);

    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'no-verify-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data' => Http::response(['success' => true], 200),
    ]);

    // Make a request using the service with verify=false
    $response = OAuth2::for('no_verify_service')->get('https://api.example.com/data');

    expect($response->status())->toBe(200)
        ->and(Cache::get('oauth2_no_verify_service_access_token'))->toBe('no-verify-token');
});

test('retries request with new token on 401 error', function (): void {

    // Set up an initial token in the cache
    $initialToken = 'initial-token';
    $newToken = 'new-token';

    Cache::put('oauth2_test_service_access_token', $initialToken, now()->addHour());
    Cache::put('oauth2_test_service_expires_at', now()->addHour()->getTimestamp(), now()->addHour());

    // First request fails with 401, second request succeeds after token refresh
    Http::fake([
        // First API request fails with 401
        'https://api.example.com/data' => Http::sequence()
            ->push(['error' => 'unauthorized', 'message' => 'Token expired'], 401)
            ->push(['success' => true, 'data' => 'refreshed'], 200),

        // Token refresh request succeeds
        'https://auth.example.com/token' => Http::response([
            'access_token' => $newToken,
            'expires_in' => 3600,
        ]),
    ]);

    // Make the request
    $response = OAuth2::for('test_service')->get('https://api.example.com/data');

    // Assert that the request succeeded after token refresh
    expect($response->status())->toBe(200)
        ->and($response->json('success'))->toBeTrue()
        ->and($response->json('data'))->toBe('refreshed')
        ->and(Cache::get('oauth2_test_service_access_token'))->toBe($newToken);

    // Verify that both the API request and token refresh request were made
    Http::assertSentCount(3); // Initial request + token refresh + retry request
});
