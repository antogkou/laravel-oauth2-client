<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Facades;

use Antogkou\LaravelOAuth2Client\OAuth2Client;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Response get(string $url, array<string, mixed> $options = [])
 * @method static Response post(string $url, array<string, mixed> $options = [])
 * @method static Response put(string $url, array<string, mixed> $options = [])
 * @method static Response patch(string $url, array<string, mixed> $options = [])
 * @method static Response delete(string $url, array<string, mixed> $options = [])
 * @method static Response request(string $method, string $url, array<string, mixed> $options = [])
 * @method static OAuth2Client for('default'|string $service)
 *
 * @see OAuth2Client
 */
final class OAuth2 extends Facade
{
    /**
     * @param 'default'|string $service
     */
    public static function for(string $service): OAuth2Client
    {
        return app(OAuth2Client::class, ['service' => $service]);
    }

    protected static function getFacadeAccessor(): string
    {
        return OAuth2Client::class;
    }
}
