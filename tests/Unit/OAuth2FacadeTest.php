<?php

declare(strict_types=1);

use Antogkou\LaravelOAuth2Client\Facades\OAuth2;
use Antogkou\LaravelOAuth2Client\OAuth2Client;

test('OAuth2 facade accessor returns OAuth2Client class', function () {
    $method = new ReflectionMethod(OAuth2::class, 'getFacadeAccessor');
    $method->setAccessible(true);
    expect($method->invoke(null))->toBe(OAuth2Client::class);
}); 