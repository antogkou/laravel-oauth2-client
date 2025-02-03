# Laravel OAuth2 Client

[![Tests](https://github.com/antogkou/laravel-oauth2-client/actions/workflows/tests.yml/badge.svg)](https://github.com/antogkou/laravel-oauth2-client/actions)
[![License](https://img.shields.io/github/license/antogkou/laravel-oauth2-client)](LICENSE.md)
[![Packagist](https://img.shields.io/packagist/v/antogkou/laravel-oauth2-client)](https://packagist.org/packages/antogkou/laravel-oauth2-client)

A robust Laravel package for OAuth2 Client Credentials flow integration with automatic token management.

## Features

- 🚀 **Automatic Token Management** - Handles token acquisition and refresh
- 🔧 **Multiple Service Support** - Configure multiple OAuth2 providers
- 📦 **Caching** - Stores tokens securely using Laravel Cache
- 📝 **Structured Logging** - Detailed error logging for failed requests
- 🛡️ **Security** - Sensitive data redaction in logs
- ✅ **Laravel 11+ Ready** - Full support for latest Laravel versions

## Installation

```bash
composer require antogkou/laravel-oauth2-client
```

Publish configuration file:

```bash
php artisan vendor:publish --tag=oauth2-client-config
```

## Configuration

### Config File (`config/oauth2-client.php`)

```php
return [
    'services' => [
        'service1' => [
            'client_id' => env('SERVICE1_CLIENT_ID'),
            'client_secret' => env('SERVICE1_CLIENT_SECRET'),
            'token_url' => env('SERVICE1_TOKEN_URL'),
            'scope' => env('SERVICE1_SCOPE', ''),
        ],
    ],
    'cache_prefix' => 'oauth2_',
    'expiration_buffer' => 60, // Seconds before expiration to renew token
    'logging' => [
        'enabled' => true,
        'level' => 'error',
        'redact' => [
            'headers.authorization',
            'body.password',
            'response.access_token'
        ]
    ]
];
```

### Environment Variables

```ini
# Service 1 Configuration
SERVICE1_CLIENT_ID = your_client_id
SERVICE1_CLIENT_SECRET = your_client_secret
SERVICE1_TOKEN_URL = https://auth.service.com/token
SERVICE1_SCOPE = api
```

## Usage

### Basic API Calls

```php
use Antogkou\LaravelOAuth2Client\Facades\OAuth2;

// GET request
$response = OAuth2::for('service1')->get('https://api.service.com/data');

// POST request
$response = OAuth2::for('service1')->post('https://api.service.com/data', [
    'key' => 'value'
]);

// Get JSON response
$data = $response->json();
```

### Available Methods

```php
OAuth2::for('service1')->get($url, $options);
OAuth2::for('service1')->post($url, $options);
OAuth2::for('service1')->put($url, $options);
OAuth2::for('service1')->patch($url, $options);
OAuth2::for('service1')->delete($url, $options);
OAuth2::for('service1')->request($method, $url, $options);
```

## Error Handling

The package throws `Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception` for errors:

```php
try {
    OAuth2::for('service1')->get('https://api.service.com/data');
} catch (OAuth2Exception $e) {
    // Handle exception
    $errorContext = $e->getContext();
}
```

## Testing

```bash
# Run tests
composer test

# Static analysis
composer test:types

# Code formatting
composer lint
```

## Security

- 🔒 **Never commit client secrets** - Always use environment variables
- 🛡️ **Log Redaction** - Automatically redacts sensitive data from logs
- 🔄 **Token Security** - Tokens are never stored in permanent storage

## Contributing

Contributions welcome! Please follow:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request

## License

MIT License - See [LICENSE](LICENSE.md) for details.

## Support

For issues and feature requests,
please [create a GitHub issue](https://github.com/antogkou/laravel-oauth2-client/issues).


