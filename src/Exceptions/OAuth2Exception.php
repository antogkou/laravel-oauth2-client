<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class OAuth2Exception extends RuntimeException implements Responsable
{
    private const DEFAULT_STATUS_CODE = Response::HTTP_INTERNAL_SERVER_ERROR;

    /** @var array<string, mixed> */
    private array $context = [];

    private int $statusCode = 0;

    /** @var array<string, mixed>|null */
    private ?array $responseData = null;

    /**
     * Create a new OAuth2Exception instance.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The HTTP status code
     * @param  Throwable|null  $previous  The previous exception
     * @param  array<string, mixed>  $context  Additional context information about the exception
     */
    public function __construct(
        string $message,
        int $code = self::DEFAULT_STATUS_CODE,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $code;

        if (!empty($context)) {
            $this->withContext($context);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        // Merge the new context with the existing one instead of replacing it
        $this->context = array_merge($this->context, $context);

        // Extract status code and response data from context if available
        if (isset($context['status'])) {
            $this->statusCode = (int) $context['status'];
        }

        if (isset($context['response'])) {
            $this->responseData = $context['response'];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the HTTP status code of the failed response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response data from the failed request.
     *
     * @return array<string, mixed>|null
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * Get a specific error field from the response data.
     *
     * @param  string  $field  The field to extract from the response
     * @param  mixed  $default  Default value if field doesn't exist
     * @return mixed The value of the field or the default
     */
    public function getErrorField(string $field, mixed $default = null): mixed
    {
        if ($this->responseData === null) {
            return $default;
        }

        return $this->responseData[$field] ?? $default;
    }

    /**
     * Check if the response contains a specific error code.
     *
     * @param  string  $errorCode  The error code to check for
     * @param  string  $field  The field in the response that contains the error code
     * @return bool True if the error code matches
     */
    public function hasErrorCode(string $errorCode, string $field = 'error'): bool
    {
        return $this->getErrorField($field) === $errorCode;
    }

    /**
     * Get the clean error message without the service and status prefix.
     *
     * @return string The clean error message
     */
    public function getCleanMessage(): string
    {
        $message = $this->getMessage();

        // Common patterns for error message prefixes
        $patterns = [
            '/API request failed for service .+ with status \d+: (.+)/',
            '/Failed to obtain access token for service: .+ with status \d+: (.+)/',
            '/API request failed for service .+: (.+)/',
            '/Failed to obtain access token for service: .+: (.+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1];
            }
        }

        return $message;
    }

    /**
     * Convert the exception to an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toResponse($request): JsonResponse
    {
        $statusCode = $this->isValidHttpStatusCode($this->getStatusCode())
            ? $this->getStatusCode()
            : self::DEFAULT_STATUS_CODE;

        $response = [
            'message' => $this->getCleanMessage(),
            'code' => $this->getCode(),
            'context' => $this->getContext(),
        ];

        // Include response data if available
        if ($this->responseData !== null) {
            $response['response_data'] = $this->responseData;
        }

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Check if the given code is a valid HTTP status code.
     *
     * @param  int  $code
     * @return bool
     */
    private function isValidHttpStatusCode(int $code): bool
    {
        return $code >= 100 && $code < 600;
    }
}
