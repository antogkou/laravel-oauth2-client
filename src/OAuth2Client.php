<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use DateTimeImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * @method Response get(string $url, array $options = [])
 * @method Response post(string $url, array $options = [])
 * @method Response put(string $url, array $options = [])
 * @method Response patch(string $url, array $options = [])
 * @method Response delete(string $url, array $options = [])
 */
final class OAuth2Client
{
    /** @var array{
     *     token_url: string,
     *     client_id: string,
     *     client_secret: string,
     *     scope?: string
     * }
     */
    private array $config;

    private string $accessToken = '';

    private DateTimeImmutable $expiresAt;

    public function __construct(private readonly string $serviceName)
    {
        /** @var mixed $rawConfig */
        $rawConfig = config("oauth2-client.services.{$this->serviceName}", []);

        if (! is_array($rawConfig)) {
            throw new OAuth2Exception("Invalid configuration type for service: {$this->serviceName}");
        }

        if ($rawConfig === []) {
            throw new OAuth2Exception("No configuration found for service: {$this->serviceName}");
        }

        if (! isset($rawConfig['token_url'], $rawConfig['client_id'], $rawConfig['client_secret']) ||
            ! is_string($rawConfig['token_url']) ||
            ! is_string($rawConfig['client_id']) ||
            ! is_string($rawConfig['client_secret']) ||
            (isset($rawConfig['scope']) && ! is_string($rawConfig['scope']))
        ) {
            throw new OAuth2Exception("Invalid configuration format for service: {$this->serviceName}");
        }

        /** @var array{
         *     token_url: string,
         *     client_id: string,
         *     client_secret: string,
         *     scope?: string
         * } $rawConfig
         */
        $this->config = $rawConfig;
        $this->expiresAt = new DateTimeImmutable('@0'); // Initialize with Unix epoch
    }

    /**
     * @param  array{0: string, 1?: array<string, mixed>}  $parameters
     *
     * @throws OAuth2Exception
     */
    public function __call(string $method, array $parameters): Response
    {
        if (! isset($parameters[0]) || ! is_string($parameters[0])) {
            throw new OAuth2Exception('URL parameter must be a string');
        }

        /** @var array<string, mixed> */
        $options = isset($parameters[1]) && is_array($parameters[1]) ? $parameters[1] : [];

        return $this->request($method, $parameters[0], $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        $this->ensureValidToken();

        try {
            $response = Http::withToken($this->accessToken)
                ->send($method, $url, $options);

            if ($response->failed()) {
                $this->logApiError($method, $url, $response);
                $response->throw();
            }

            return $response;
        } catch (Throwable $e) {
            Log::error("API request exception for service {$this->serviceName}", [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new OAuth2Exception(
                "API request failed for service {$this->serviceName}: ".$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function ensureValidToken(): void
    {
        $this->getCachedToken();

        if (! $this->hasValidToken()) {
            $this->fetchNewToken();
        }
    }

    private function getCachedToken(): void
    {
        /** @var string */
        $prefix = config('oauth2-client.cache_prefix', '');

        /** @var string */
        $accessToken = Cache::get("{$prefix}{$this->serviceName}_access_token", '');
        $this->accessToken = $accessToken;

        /** @var int */
        $timestamp = Cache::get("{$prefix}{$this->serviceName}_expires_at", 0);
        $expiresAt = DateTimeImmutable::createFromFormat('U', (string) $timestamp);

        if ($expiresAt === false) {
            throw new RuntimeException('Failed to create DateTimeImmutable from timestamp');
        }

        $this->expiresAt = $expiresAt;
    }

    private function hasValidToken(): bool
    {
        return $this->accessToken !== '' &&
            $this->expiresAt->getTimestamp() > (time() + config('oauth2-client.expiration_buffer', 0));
    }

    private function fetchNewToken(): void
    {
        try {
            $response = Http::asForm()->post($this->config['token_url'], [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'scope' => $this->config['scope'] ?? '',
            ]);

            if ($response->successful()) {
                /** @var array{access_token: string, expires_in: int} */
                $data = $response->json();
                $this->storeToken($data['access_token'], $data['expires_in']);
            } else {
                $this->logTokenError($response);
                throw new OAuth2Exception(
                    "Failed to obtain access token for service: {$this->serviceName}",
                    $response->status()
                );
            }
        } catch (Throwable $e) {
            Log::error("Token fetch exception for service {$this->serviceName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new OAuth2Exception(
                "Token fetch failed for service {$this->serviceName}: ".$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function storeToken(string $accessToken, int $expiresIn): void
    {
        $expiresAt = now()->addSeconds($expiresIn);
        /** @var string */
        $prefix = config('oauth2-client.cache_prefix', '');

        Cache::put(
            "{$prefix}{$this->serviceName}_access_token",
            $accessToken,
            $expiresAt
        );

        Cache::put(
            "{$prefix}{$this->serviceName}_expires_at",
            $expiresAt->getTimestamp(),
            $expiresAt
        );

        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt->toImmutable();
    }

    private function logTokenError(Response $response): void
    {
        Log::error("Token fetch failed for service {$this->serviceName}", [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'response' => $response->body(),
            'service' => $this->serviceName,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function logApiError(string $method, string $url, Response $response): void
    {
        Log::error("API request failed for service {$this->serviceName}", [
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'response' => $response->body(),
            'service' => $this->serviceName,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
