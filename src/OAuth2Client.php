<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client;

use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;
use DateTimeImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OAuth2Client
{
    private array $config;

    private string $accessToken;

    private DateTimeImmutable $expiresAt;

    public function __construct(private readonly string $serviceName)
    {
        $this->config = config("oauth2-client.services.{$this->serviceName}", []);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->request($method, ...$parameters);
    }

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

        if (!$this->hasValidToken()) {
            $this->fetchNewToken();
        }
    }

    private function getCachedToken(): void
    {
        $prefix = config('oauth2-client.cache_prefix');
        $this->accessToken = Cache::get("{$prefix}{$this->serviceName}_access_token", '');
        $this->expiresAt = DateTimeImmutable::createFromFormat(
            'U',
            (string) Cache::get("{$prefix}{$this->serviceName}_expires_at", 0)
        );
    }

    private function hasValidToken(): bool
    {
        return $this->accessToken &&
            $this->expiresAt->getTimestamp() > (time() + config('oauth2-client.expiration_buffer'));
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
        $prefix = config('oauth2-client.cache_prefix');

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
