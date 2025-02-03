<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client;

use Antogkou\LaravelOAuth2Client\Console\GenerateOAuth2TypesCommand;
use Illuminate\Support\ServiceProvider;

final class OAuth2ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OAuth2Client::class,
            fn ($app, array $parameters): OAuth2Client => new OAuth2Client($parameters['service']));

        $this->mergeConfigFrom(
            __DIR__.'/../config/oauth2-client.php', 'oauth2-client'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOAuth2TypesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/oauth2-client.php' => config_path('oauth2-client.php'),
            ], 'oauth2-client-config');
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oauth2-client.php' => config_path('oauth2-client.php'),
        ], 'oauth2-client-config');
    }
}
