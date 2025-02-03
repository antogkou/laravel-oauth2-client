<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests;

use Antogkou\LaravelOAuth2Client\OAuth2ClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OAuth2ClientServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure test environment
        $app['config']->set('oauth2-client.services.test_service', [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'token_url' => 'https://auth.example.com/token',
            'scope' => 'api',
        ]);
    }
}
