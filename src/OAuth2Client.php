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
 * OAuth2 Client for handling API requests with automatic token management.
 *
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
     *     scope?: string,
     *     verify?: bool
     * }
     */
    private array $config;

    private string $accessToken = '';

    private DateTimeImmutable $expiresAt;

    /**
     * Create a new OAuth2 client instance.
     *
     * @param  string  $serviceName  The configured OAuth2 service name
     *
     * @throws OAuth2Exception If the service configuration is invalid
     */
    public function __construct(private readonly string $serviceName)
    {
        /** @var mixed $rawConfig */
        $rawConfig = config("oauth2-client.services.{$this->serviceName}", []);

        if (! is_array($rawConfig)) {
            throw $this->createConfigException('Invalid configuration type for service');
        }

        if ($rawConfig === []) {
            throw $this->createConfigException('No configuration found for service');
        }

        if (! isset($rawConfig['token_url'], $rawConfig['client_id'], $rawConfig['client_secret']) ||
            ! is_string($rawConfig['token_url']) ||
            ! is_string($rawConfig['client_id']) ||
            ! is_string($rawConfig['client_secret']) ||
            (isset($rawConfig['scope']) && ! is_string($rawConfig['scope'])) ||
            (isset($rawConfig['verify']) && ! is_bool($rawConfig['verify']))
        ) {
            throw $this->createConfigException('Invalid configuration format for service');
        }

        /** @var array{
         *     token_url: string,
         *     client_id: string,
         *     client_secret: string,
         *     scope?: string,
         *     verify?: bool
         * } $rawConfig
         */
        $this->config = $rawConfig;
        $this->expiresAt = new DateTimeImmutable('@0'); // Initialize with Unix epoch
    }

    /**
     * Magic method to handle HTTP requests.
     *
     * @param  string  $method  HTTP method name (get, post, put, patch, delete)
     * @param  array<int, mixed>  $parameters  [url, options]
     *
     * @throws OAuth2Exception
     */
    public function __call(string $method, array $parameters): Response
    {
        if (empty($parameters[0]) || ! is_string($parameters[0])) {
            throw new OAuth2Exception('URL parameter must be a string');
        }

        /** @var array<string, mixed> */
        $options = isset($parameters[1]) && is_array($parameters[1]) ? $parameters[1] : [];

        return $this->request($method, $parameters[0], $options);
    }

    /**
     * Make an HTTP request with OAuth2 authentication.
     *
     * @param  string  $method  HTTP method
     * @param  string  $url  Request URL
     * @param  array<string, mixed>  $options  Request options
     *
     * @throws OAuth2Exception
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        $this->ensureValidToken();

        try {
            $http = Http::withToken($this->accessToken)
                ->acceptJson();

            // Disable SSL verification if configured
            if ($this->shouldDisableSSLVerification()) {
                $http = $http->withoutVerifying();
            }

            $response = $http->send($method, $url, $options);

            if ($response->failed()) {
                return $this->handleFailedResponse($method, $url, $response);
            }

            return $response;
        } catch (Throwable $e) {
            return $this->handleRequestException($method, $url, $e, $options);
        }
    }

    /**
     * Determine if SSL verification should be disabled.
     *
     * @return bool Returns true if verify is explicitly set to false in the config
     */
    private function shouldDisableSSLVerification(): bool
    {
        return isset($this->config['verify']) && $this->config['verify'] === false;
    }

    /**
     * Handle a failed HTTP response.
     *
     * @param  string  $method  HTTP method
     * @param  string  $url  Request URL
     * @param  Response  $response  Failed response
     *
     * @throws OAuth2Exception
     */
    private function handleFailedResponse(string $method, string $url, Response $response): never
    {
        $responseData = $this->safeJsonDecode($response->body());
        $statusCode = $response->status();

        $context = [
            'method' => $method,
            'url' => $url,
            'status' => $statusCode,
            'response' => $responseData,
            'service' => $this->serviceName,
        ];

        Log::error("API request failed for service {$this->serviceName}", $context);

        // Build a more detailed error message
        $message = "API request failed for service {$this->serviceName} with status {$statusCode}";

        // Add error details from the response if available
        if (is_array($responseData)) {
            if (isset($responseData['error'])) {
                $message .= ": {$responseData['error']}";

                if (isset($responseData['error_description'])) {
                    $message .= " - {$responseData['error_description']}";
                }
            } elseif (isset($responseData['message'])) {
                $message .= ": {$responseData['message']}";
            }
        }

        throw (new OAuth2Exception($message, $statusCode))
            ->withContext($context);
    }

    /**
     * Handle a request exception.
     *
     * @param  string  $method  HTTP method
     * @param  string  $url  Request URL
     * @param  Throwable  $exception  The exception that occurred
     * @param  array<string, mixed>  $options  Request options that were used
     *
     * @throws OAuth2Exception
     */
    private function handleRequestException(string $method, string $url, Throwable $exception, array $options): never
    {
        // Extract HTTP status code if available
        $statusCode = $exception->getCode() ?: 0;

        // Try to extract response data if it's a HTTP client exception
        $responseData = null;
        if (method_exists($exception, 'getResponse') && $exception->getResponse() !== null) {
            $response = $exception->getResponse();
            if (method_exists($response, 'body')) {
                $responseData = $this->safeJsonDecode($response->body());
            }
            if (method_exists($response, 'status')) {
                $statusCode = $response->status();
            }
        } elseif (property_exists($exception, 'response') && isset($exception->response)) {
            $response = $exception->response;
            if (method_exists($response, 'body')) {
                $responseData = $this->safeJsonDecode($response->body());
            }
            if (method_exists($response, 'status')) {
                $statusCode = $response->status();
            }
        }

        $context = [
            'method' => $method,
            'url' => $url,
            'error' => $exception->getMessage(),
            'service' => $this->serviceName,
            'options' => $this->sanitizeOptions($options),
        ];

        // Add response data to context if available
        if ($responseData !== null) {
            $context['response'] = $responseData;
            $context['status'] = $statusCode;
        }

        Log::error("API request exception for service {$this->serviceName}", $context);

        // Build a more detailed error message
        $message = "API request failed for service {$this->serviceName}";

        // Add status code if available
        if ($statusCode > 0) {
            $message .= " with status {$statusCode}";
        }

        // If the exception is already an OAuth2Exception, we don't want to nest the error messages
        if ($exception instanceof OAuth2Exception) {
            // Extract the original error message without the service and status prefix
            $originalMessage = $exception->getMessage();

            // Extract the actual error message without the prefix
            if (preg_match('/API request failed for service .+ with status \d+: (.+)/', $originalMessage, $matches)) {
                $message .= ': '.$matches[1];
            } else {
                // If we can't extract the actual error message, use the original message
                $message .= ': '.$originalMessage;
            }
        } else {
            // For other exceptions, add the exception message
            $message .= ': '.$exception->getMessage();
        }

        // Add error details from the response if available
        if (is_array($responseData)) {
            if (isset($responseData['error'])) {
                $message .= " ({$responseData['error']})";

                if (isset($responseData['error_description'])) {
                    $message .= " - {$responseData['error_description']}";
                }
            } elseif (isset($responseData['message'])) {
                $message .= " ({$responseData['message']})";
            }
        }

        throw (new OAuth2Exception(
            $message,
            $statusCode,
            $exception
        ))->withContext($context);
    }

    /**
     * Sanitize request options to remove sensitive data.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function sanitizeOptions(array $options): array
    {
        $sanitized = $options;

        // Remove potentially sensitive data
        if (isset($sanitized['json'])) {
            $sanitized['json'] = '[Redacted for security]';
        }

        if (isset($sanitized['form_params'])) {
            $sanitized['form_params'] = '[Redacted for security]';
        }

        if (isset($sanitized['multipart'])) {
            $sanitized['multipart'] = '[Redacted for security]';
        }

        return $sanitized;
    }

    /**
     * Ensure a valid access token is available.
     *
     * @throws OAuth2Exception
     */
    private function ensureValidToken(): void
    {
        $this->getCachedToken();

        if (! $this->hasValidToken()) {
            $this->fetchNewToken();
        }
    }

    /**
     * Get cached token from Laravel Cache.
     */
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

    /**
     * Check if the current token is valid.
     */
    private function hasValidToken(): bool
    {
        $bufferSeconds = (int) config('oauth2-client.expiration_buffer', 60);

        return $this->accessToken !== '' &&
            $this->expiresAt->getTimestamp() > (time() + $bufferSeconds);
    }

    /**
     * Fetch a new access token from the OAuth2 server.
     *
     * @throws OAuth2Exception
     */
    private function fetchNewToken(): void
    {
        try {
            $http = Http::asForm()
                ->acceptJson();

            // Disable SSL verification if configured
            if (isset($this->config['verify']) && $this->config['verify'] === false) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post($this->config['token_url'], [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'scope' => $this->config['scope'] ?? '',
            ]);

            if ($response->successful()) {
                $this->processTokenResponse($response);
            } else {
                $this->handleFailedTokenResponse($response);
            }
        } catch (Throwable $e) {
            $this->handleTokenException($e);
        }
    }

    /**
     * Process a successful token response.
     *
     * @throws OAuth2Exception
     */
    private function processTokenResponse(Response $response): void
    {
        $data = $response->json();

        if (! isset($data['access_token']) || ! isset($data['expires_in'])) {
            $context = [
                'response' => $this->safeJsonDecode($response->body()),
                'service' => $this->serviceName,
            ];

            throw (new OAuth2Exception(
                "Invalid token response format for service: {$this->serviceName}"
            ))->withContext($context);
        }

        $this->storeToken($data['access_token'], (int) $data['expires_in']);
    }

    /**
     * Handle a failed token response.
     *
     * @throws OAuth2Exception
     */
    private function handleFailedTokenResponse(Response $response): never
    {
        $responseData = $this->safeJsonDecode($response->body());
        $statusCode = $response->status();

        $context = [
            'status' => $statusCode,
            'response' => $responseData,
            'service' => $this->serviceName,
        ];

        Log::error("Token fetch failed for service {$this->serviceName}", $context);

        // Build a more detailed error message
        $message = "Failed to obtain access token for service: {$this->serviceName} with status {$statusCode}";

        // Add error details from the response if available
        if (is_array($responseData)) {
            if (isset($responseData['error'])) {
                $message .= ": {$responseData['error']}";

                if (isset($responseData['error_description'])) {
                    $message .= " - {$responseData['error_description']}";
                }
            } elseif (isset($responseData['message'])) {
                $message .= ": {$responseData['message']}";
            }
        }

        throw (new OAuth2Exception(
            $message,
            $statusCode
        ))->withContext($context);
    }

    /**
     * Handle a token fetch exception.
     *
     * @throws OAuth2Exception
     */
    private function handleTokenException(Throwable $exception): never
    {
        // Extract HTTP status code if available
        $statusCode = $exception->getCode() ?: 0;

        // Try to extract response data if it's a HTTP client exception
        $responseData = null;
        if (method_exists($exception, 'getResponse') && $exception->getResponse() !== null) {
            $response = $exception->getResponse();
            if (method_exists($response, 'body')) {
                $responseData = $this->safeJsonDecode($response->body());
            }
            if (method_exists($response, 'status')) {
                $statusCode = $response->status();
            }
        } elseif (property_exists($exception, 'response') && isset($exception->response)) {
            $response = $exception->response;
            if (method_exists($response, 'body')) {
                $responseData = $this->safeJsonDecode($response->body());
            }
            if (method_exists($response, 'status')) {
                $statusCode = $response->status();
            }
        }

        $context = [
            'error' => $exception->getMessage(),
            'service' => $this->serviceName,
        ];

        // Add response data to context if available
        if ($responseData !== null) {
            $context['response'] = $responseData;
            $context['status'] = $statusCode;
        }

        Log::error("Token fetch exception for service {$this->serviceName}", $context);

        // Build a more detailed error message
        $message = "Failed to obtain access token for service: {$this->serviceName}";

        // Add status code if available
        if ($statusCode > 0) {
            $message .= " with status {$statusCode}";
        }

        // Add exception message
        $message .= ': '.$exception->getMessage();

        // Add error details from the response if available
        if (is_array($responseData)) {
            if (isset($responseData['error'])) {
                $message .= " ({$responseData['error']})";

                if (isset($responseData['error_description'])) {
                    $message .= " - {$responseData['error_description']}";
                }
            } elseif (isset($responseData['message'])) {
                $message .= " ({$responseData['message']})";
            }
        }

        throw (new OAuth2Exception(
            $message,
            $statusCode,
            $exception
        ))->withContext($context);
    }

    /**
     * Store the access token in cache.
     */
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

    /**
     * Safely decode JSON string.
     */
    private function safeJsonDecode(string $json): mixed
    {
        $data = json_decode($json, true);

        return $data ?? $json;
    }

    /**
     * Create a configuration exception.
     *
     * @param  string  $message  The error message for the exception.
     * @return OAuth2Exception The created configuration exception.
     */
    private function createConfigException(string $message): OAuth2Exception
    {
        return (new OAuth2Exception("{$message}: {$this->serviceName}"))
            ->withContext(['service' => $this->serviceName]);
    }
}
