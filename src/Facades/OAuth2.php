<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Facades;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use Antogkou\LaravelOAuth2Client\OAuth2Client;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the OAuth2 Client.
 *
 * @method static Response get(string $url, array $options = []) Make a GET request
 * @method static Response post(string $url, array $options = []) Make a POST request
 * @method static Response put(string $url, array $options = []) Make a PUT request
 * @method static Response patch(string $url, array $options = []) Make a PATCH request
 * @method static Response delete(string $url, array $options = []) Make a DELETE request
 * @method static Response request(string $method, string $url, array $options = []) Make a custom HTTP request
 *
 * @throws OAuth2Exception When a request fails or configuration is invalid
 *
 * @see OAuth2Client
 */
final class OAuth2 extends Facade
{
    /**
     * Get an OAuth2 client for a configured service.
     *
     * @param  string  $service  The service name (see Antogkou\LaravelOAuth2Client\Types\OAuth2Services for available values)
     *
     * @see Antogkou\LaravelOAuth2Client\Types\OAuth2Services
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
