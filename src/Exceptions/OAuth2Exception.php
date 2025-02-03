<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Exceptions;

use RuntimeException;

final class OAuth2Exception extends RuntimeException
{
    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
