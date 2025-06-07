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
