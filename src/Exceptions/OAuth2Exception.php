<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Exceptions;

use RuntimeException;

final class OAuth2Exception extends RuntimeException
{
    private array $context = [];

    public function withContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
