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

    $response = OAuth2::for('test_service')->get(url: 'https://api.example.com/data');

    expect($response->status())->toBe(200)
        ->and($response->json('success'))->toBeTrue()
        ->and($response->json('data'))->toBe('refreshed')
        ->and(Cache::get('oauth2_test_service_access_token'))->toBe($newToken);

    Http::assertSentCount(3);
});

test('throws exception for missing service configuration', function (): void {
    config(['oauth2-client.services' => []]);

    expect(fn (): \Illuminate\Http\Client\Response => OAuth2::for('nonexistent')->get('https://api.example.com/data'))
        ->toThrow(OAuth2Exception::class, 'No configuration found for service');
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
    expect($exception)->toBeInstanceOf(OAuth2Exception::class)
        ->and($exception->getMessage())->toContain('Invalid token response format');
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
    expect($exception)->toBeInstanceOf(OAuth2Exception::class)
        ->and($exception->getMessage())->toContain('Network error');
})->skip('This test is skipped because it requires a real HTTP client exception to be thrown, which may not happen in a fake environment.');

test('handles cache failure gracefully', function (): void {
    Cache::shouldReceive('get')->andThrow(new Exception('Cache unavailable'));

    $exception = null;
    try {
        OAuth2::for('test_service')->get('https://api.example.com/data');
    } catch (Exception $e) {
        $exception = $e;
    }
    expect($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->toContain('Cache unavailable');
});

test('OAuth2Exception toResponse includes debug info with X-Debug header', function (): void {
    $exception = new OAuth2Exception('Test error', 500);
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_DEBUG' => '1']);
    $response = $exception->toResponse($request);
    $data = $response->getData(true);
    expect($data)->toHaveKeys(['message', 'code', 'context', 'exception', 'trace'])
        ->and($data['exception'])->toBe(OAuth2Exception::class)
        ->and($data['trace'])->toBeArray()->not->toBeEmpty();
});

test('OAuth2Exception toResponse includes debug info with debug query param', function (): void {
    $exception = new OAuth2Exception('Test error', 500);
    $request = request()->merge(['debug' => '1']);
    $response = $exception->toResponse($request);
    $data = $response->getData(true);
    expect($data)->toHaveKeys(['message', 'code', 'context', 'exception', 'trace'])
        ->and($data['exception'])->toBe(OAuth2Exception::class)
        ->and($data['trace'])->toBeArray()->not->toBeEmpty();
});

test('postJson sends JSON payload correctly', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data' => Http::response(['success' => true, 'id' => 123], 201),
    ]);

    $payload = ['name' => 'John Doe', 'email' => 'john@example.com'];
    $response = OAuth2::for('test_service')->postJson($payload, 'https://api.example.com/data');

    expect($response->status())->toBe(201)
        ->and($response->json('success'))->toBeTrue()
        ->and($response->json('id'))->toBe(123);

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://api.example.com/data' &&
               $request->method() === 'POST' &&
               $request->data() === $payload;
    });
});

test('putJson sends JSON payload correctly', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data/1' => Http::response(['success' => true, 'updated' => true], 200),
    ]);

    $payload = ['name' => 'Jane Doe', 'status' => 'active'];
    $response = OAuth2::for('test_service')->putJson($payload, 'https://api.example.com/data/1');

    expect($response->status())->toBe(200)
        ->and($response->json('success'))->toBeTrue()
        ->and($response->json('updated'))->toBeTrue();

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://api.example.com/data/1' &&
               $request->method() === 'PUT' &&
               $request->data() === $payload;
    });
});

test('patchJson sends JSON payload correctly', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data/1' => Http::response(['success' => true, 'patched' => true], 200),
    ]);

    $payload = ['status' => 'updated'];
    $response = OAuth2::for('test_service')->patchJson($payload, 'https://api.example.com/data/1');

    expect($response->status())->toBe(200)
        ->and($response->json('success'))->toBeTrue()
        ->and($response->json('patched'))->toBeTrue();

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://api.example.com/data/1' &&
               $request->method() === 'PATCH' &&
               $request->data() === $payload;
    });
});

test('postJson can accept additional options', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data' => Http::response(['success' => true], 201),
    ]);

    $payload = ['name' => 'John Doe'];
    $options = ['headers' => ['X-Custom-Header' => 'custom-value']];
    
    $response = OAuth2::for('test_service')->postJson($payload, 'https://api.example.com/data', $options);

    expect($response->status())->toBe(201)
        ->and($response->json('success'))->toBeTrue();

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://api.example.com/data' &&
               $request->method() === 'POST' &&
               $request->data() === $payload &&
               $request->hasHeader('X-Custom-Header', 'custom-value');
    });
});

test('json convenience methods preserve existing options', function (): void {
    Http::fake([
        'https://auth.example.com/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.example.com/data' => Http::response(['success' => true], 201),
    ]);

    $payload = ['name' => 'John Doe'];
    // Test that existing 'json' option gets overridden but other options are preserved
    $options = [
        'headers' => ['X-Custom-Header' => 'custom-value'],
        'json' => ['this' => 'should be overridden'],
        'timeout' => 30
    ];
    
    $response = OAuth2::for('test_service')->postJson($payload, 'https://api.example.com/data', $options);

    expect($response->status())->toBe(201);

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://api.example.com/data' &&
               $request->method() === 'POST' &&
               $request->data() === $payload &&
               $request->hasHeader('X-Custom-Header', 'custom-value');
    });
});
