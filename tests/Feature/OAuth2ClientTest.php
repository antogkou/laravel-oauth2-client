<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Feature;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use Antogkou\LaravelOAuth2Client\Facades\OAuth2;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
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
        'https://api.example.com/data' => Http::response('Should not reach here', 500),
    ]);

    try {
        OAuth2::for('test_service')->get('https://api.example.com/data');
    } catch (Exception $e) {
        expect($e)->toBeInstanceOf(OAuth2Exception::class);
        expect($e->getMessage())->toContain('Failed to obtain access token');
    }

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.example.com/token');
});

test('disables SSL verification when verify is false', function (): void {
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

    $response = OAuth2::for('no_verify_service')->get('https://api.example.com/data');

    expect($response->status())->toBe(200)
        ->and(Cache::get('oauth2_no_verify_service_access_token'))->toBe('no-verify-token');
});

test('retries request with new token on 401 error', function (): void {
    $initialToken = 'initial-token';
    $newToken = 'new-token';

    Cache::put('oauth2_test_service_access_token', $initialToken, now()->addHour());
    Cache::put('oauth2_test_service_expires_at', now()->addHour()->getTimestamp(), now()->addHour());

    Http::fake([
        'https://api.example.com/data' => Http::sequence()
            ->push(['error' => 'unauthorized', 'message' => 'Token expired'], 401)
            ->push(['success' => true, 'data' => 'refreshed'], 200),

        'https://auth.example.com/token' => Http::response([
            'access_token' => $newToken,
            'expires_in' => 3600,
        ]),
    ]);

    $response = OAuth2::for('test_service')->get('https://api.example.com/data');

    expect($response->status())->toBe(200)
        ->and($response->json('success'))->toBeTrue()
        ->and($response->json('data'))->toBe('refreshed')
        ->and(Cache::get('oauth2_test_service_access_token'))->toBe($newToken);

    Http::assertSentCount(3);
});

test('throws exception for missing service configuration', function (): void {
    config(['oauth2-client.services' => []]);

    $exception = null;
    try {
        OAuth2::for('nonexistent')->get('https://api.example.com/data');
    } catch (Exception $e) {
        $exception = $e;
    }
    expect($exception)->toBeInstanceOf(OAuth2Exception::class);
    expect($exception->getMessage())->toContain('No configuration found for service');
});

test('throws exception for token response missing fields', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'foo' => 'bar',
        ], 200),
    ]);

    $exception = null;
    try {
        OAuth2::for('test_service')->get('https://api.example.com/data');
    } catch (Exception $e) {
        $exception = $e;
    }
    expect($exception)->toBeInstanceOf(OAuth2Exception::class);
    expect($exception->getMessage())->toContain('Invalid token response format');
});

test('handles HTTP client exceptions gracefully', function (): void {
    Http::fake([
        'https://auth.example.com/token' => fn () => throw new Exception('Network error'),
    ]);

    $exception = null;
    try {
        OAuth2::for('test_service')->get('https://api.example.com/data');
    } catch (Exception $e) {
        $exception = $e;
    }
    expect($exception)->toBeInstanceOf(OAuth2Exception::class);
    expect($exception->getMessage())->toContain('Network error');
});

test('handles cache failure gracefully', function (): void {
    Cache::shouldReceive('get')->andThrow(new Exception('Cache unavailable'));

    $exception = null;
    try {
        OAuth2::for('test_service')->get('https://api.example.com/data');
    } catch (Exception $e) {
        $exception = $e;
    }
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toContain('Cache unavailable');
});

test('OAuth2Exception toResponse includes debug info with X-Debug header', function (): void {
    $exception = new OAuth2Exception('Test error', 500);
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_DEBUG' => '1']);
    $response = $exception->toResponse($request);
    $data = $response->getData(true);
    expect($data)->toHaveKeys(['message', 'code', 'context', 'exception', 'trace']);
    expect($data['exception'])->toBe(OAuth2Exception::class);
    expect($data['trace'])->toBeArray()->not->toBeEmpty();
});

test('OAuth2Exception toResponse includes debug info with debug query param', function (): void {
    $exception = new OAuth2Exception('Test error', 500);
    $request = request()->merge(['debug' => '1']);
    $response = $exception->toResponse($request);
    $data = $response->getData(true);
    expect($data)->toHaveKeys(['message', 'code', 'context', 'exception', 'trace']);
    expect($data['exception'])->toBe(OAuth2Exception::class);
    expect($data['trace'])->toBeArray()->not->toBeEmpty();
});
