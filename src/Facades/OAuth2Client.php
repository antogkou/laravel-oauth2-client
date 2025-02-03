<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Facades;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Response get(string $url, array $options = [])
 * @method static Response post(string $url, array $options = [])
 * @method static Response put(string $url, array $options = [])
 * @method static Response patch(string $url, array $options = [])
 * @method static Response delete(string $url, array $options = [])
 * @method static Response request(string $method, string $url, array $options = [])
 *
 * @see \Antogkou\LaravelOAuth2Client\OAuth2Client
 */
final class OAuth2Client extends Facade
{
    public static function for(string $service): \Antogkou\LaravelOAuth2Client\OAuth2Client
    {
        return app(\Antogkou\LaravelOAuth2Client\OAuth2Client::class, ['service' => $service]);
    }

    protected static function getFacadeAccessor(): string
    {
        return \Antogkou\LaravelOAuth2Client\OAuth2Client::class;
    }
}
