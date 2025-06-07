<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Unit;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;

test('getCleanMessage extracts error message without prefix', function (): void {
    // Test with API request failed message
    $exception1 = new OAuth2Exception(
        'API request failed for service foundations-core with status 410: This endpoint is deprecated. Please use the new API.',
        410
    );
    expect($exception1->getCleanMessage())->toBe('This endpoint is deprecated. Please use the new API.');

    // Test with token fetch failed message
    $exception2 = new OAuth2Exception(
        'Failed to obtain access token for service: test-service with status 401: Invalid client credentials',
        401
    );
    expect($exception2->getCleanMessage())->toBe('Invalid client credentials');

    // Test with message that doesn't match any pattern
    $exception3 = new OAuth2Exception('Some other error message', 500);
    expect($exception3->getCleanMessage())->toBe('Some other error message');
});

test('toResponse uses clean message in response', function (): void {
    $exception = new OAuth2Exception(
        'API request failed for service foundations-core with status 410: This endpoint is deprecated. Please use the new API.',
        410
    );

    $response = $exception->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content['message'])->toBe('This endpoint is deprecated. Please use the new API.');
});

test('withContext merges and extracts status/response', function (): void {
    $e = new OAuth2Exception('msg', 400);
    $e->withContext(['foo' => 'bar', 'status' => 418, 'response' => ['err' => 'x']]);
    expect($e->getContext())->toMatchArray(['foo' => 'bar', 'status' => 418, 'response' => ['err' => 'x']]);
    expect($e->getStatusCode())->toBe(418);
    expect($e->getResponseData())->toMatchArray(['err' => 'x']);
});

test('getErrorField and hasErrorCode work as expected', function (): void {
    $e = new OAuth2Exception('msg', 400);
    $e->withContext(['response' => ['error' => 'invalid', 'foo' => 'bar']]);
    expect($e->getErrorField('error'))->toBe('invalid');
    expect($e->getErrorField('foo'))->toBe('bar');
    expect($e->getErrorField('missing', 'def'))->toBe('def');
    expect($e->hasErrorCode('invalid'))->toBeTrue();
    expect($e->hasErrorCode('nope'))->toBeFalse();
    expect($e->hasErrorCode('bar', 'foo'))->toBeTrue();
});

test('toResponse includes response_data and falls back to default status', function (): void {
    $e = new OAuth2Exception('msg', 0);
    $e->withContext(['response' => ['foo' => 'bar']]);
    $resp = $e->toResponse(request());
    $data = $resp->getData(true);
    expect($data['response_data'])->toMatchArray(['foo' => 'bar']);
    expect($resp->status())->toBe(500); // default
});

test('toResponse includes debug info', function (): void {
    $e = new OAuth2Exception('msg', 400);
    $req = request()->merge(['debug' => '1']);
    $resp = $e->toResponse($req);
    $data = $resp->getData(true);
    expect($data)->toHaveKeys(['exception', 'trace']);
});
