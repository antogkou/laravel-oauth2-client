<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests;

use Antogkou\LaravelOAuth2Client\OAuth2ClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            OAuth2ClientServiceProvider::class,
        ];
    }
}
