<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Exceptions;

use RuntimeException;
use Throwable;

final class OAuth2Exception extends RuntimeException
{
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
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $code;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->context = $context;

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
}
