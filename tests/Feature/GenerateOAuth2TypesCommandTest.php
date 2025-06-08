<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('artisan command generates OAuth2 service types', function (): void {
    // Set up config with multiple services
    config(['oauth2-client.services' => [
        'service1' => [
            'client_id' => 'id1',
            'client_secret' => 'secret1',
            'token_url' => 'https://token1',
        ],
        'service2' => [
            'client_id' => 'id2',
            'client_secret' => 'secret2',
            'token_url' => 'https://token2',
        ],
    ]]);

    $generatedPath = __DIR__.'/../../src/Types/OAuth2Services.php';
    if (file_exists($generatedPath)) {
        unlink($generatedPath);
    }

    // Run the artisan command
    Artisan::call('oauth2:generate-types');

    // Assert file exists
    expect(file_exists($generatedPath))->toBeTrue();

    // Assert file contains the service names
    $content = file_get_contents($generatedPath);
    expect($content)
        ->toContain("'service1'")
        ->toContain("'service2'");

    // Clean up
    unlink($generatedPath);
});
