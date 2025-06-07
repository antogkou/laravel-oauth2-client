<?php

declare(strict_types=1);

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use Antogkou\LaravelOAuth2Client\OAuth2Client;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

describe('OAuth2Client', function (): void {
    beforeEach(function (): void {
        Cache::flush();
        config(['oauth2-client.cache_prefix' => 'oauth2_']);
    });

    it('throws on invalid config type', function (): void {
        config(['oauth2-client.services.bad' => 'not-an-array']);
        expect(fn (): OAuth2Client => new OAuth2Client('bad'))->toThrow(OAuth2Exception::class);
    });

    it('throws on missing config', function (): void {
        config(['oauth2-client.services.none' => []]);
        expect(fn (): OAuth2Client => new OAuth2Client('none'))->toThrow(OAuth2Exception::class);
    });

    it('throws on invalid config format', function (): void {
        config(['oauth2-client.services.bad2' => ['client_id' => 1, 'client_secret' => 2, 'token_url' => 3]]);
        expect(fn (): OAuth2Client => new OAuth2Client('bad2'))->toThrow(OAuth2Exception::class);
    });

    it('__call throws on missing/invalid URL', function (): void {
        $client = new OAuth2Client('test_service');
        expect(fn (): Response => $client->__call('get', []))->toThrow(OAuth2Exception::class);
        expect(fn (): Response => $client->__call('get', [123]))->toThrow(OAuth2Exception::class);
    });

    it('sanitizeOptions redacts sensitive fields', function (): void {
        $client = new OAuth2Client('test_service');
        $opts = [
            'json' => ['foo' => 'bar'],
            'form_params' => ['a' => 'b'],
            'multipart' => ['x' => 'y'],
            'other' => 'ok',
        ];
        $san = (new ReflectionMethod($client, 'sanitizeOptions'))->invoke($client, $opts);
        expect($san['json'])->toBe('[Redacted for security]');
        expect($san['form_params'])->toBe('[Redacted for security]');
        expect($san['multipart'])->toBe('[Redacted for security]');
        expect($san['other'])->toBe('ok');
    });

    it('getCachedToken throws on invalid timestamp', function (): void {
        $client = new OAuth2Client('test_service');
        Cache::put('oauth2_test_service_access_token', 'tok', 60);
        Cache::put('oauth2_test_service_expires_at', 'not-a-timestamp', 60);
        expect(fn (): mixed => (new ReflectionMethod($client, 'getCachedToken'))->invoke($client))->toThrow(RuntimeException::class);
    });

    it('hasValidToken returns false for expired/empty, true for valid', function (): void {
        $client = new OAuth2Client('test_service');
        $clientReflection = new ReflectionClass($client);
        $accessTokenProp = $clientReflection->getProperty('accessToken');
        $expiresAtProp = $clientReflection->getProperty('expiresAt');
        $accessTokenProp->setValue($client, '');
        $expiresAtProp->setValue($client, new DateTimeImmutable('@0'));
        $hasValid = (new ReflectionMethod($client, 'hasValidToken'))->invoke($client);
        expect($hasValid)->toBeFalse();
        $accessTokenProp->setValue($client, 'tok');
        $expiresAtProp->setValue($client, (new DateTimeImmutable())->modify('+1 hour'));
        $hasValid2 = (new ReflectionMethod($client, 'hasValidToken'))->invoke($client);
        expect($hasValid2)->toBeTrue();
    });

    it('safeJsonDecode returns array or raw string', function (): void {
        $client = new OAuth2Client('test_service');
        $arr = (new ReflectionMethod($client, 'safeJsonDecode'))->invoke($client, '{"foo":"bar"}');
        expect($arr)->toMatchArray(['foo' => 'bar']);
        $raw = (new ReflectionMethod($client, 'safeJsonDecode'))->invoke($client, 'not-json');
        expect($raw)->toBe('not-json');
    });

    it('createConfigException returns exception with context', function (): void {
        $client = new OAuth2Client('test_service');
        $ex = (new ReflectionMethod($client, 'createConfigException'))->invoke($client, 'fail');
        expect($ex)->toBeInstanceOf(OAuth2Exception::class);
        expect($ex->getContext())->toMatchArray(['service' => 'test_service']);
    });
});
