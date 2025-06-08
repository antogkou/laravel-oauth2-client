# Laravel OAuth2 Client

[![Tests](https://github.com/antogkou/laravel-oauth2-client/actions/workflows/tests.yml/badge.svg)](https://github.com/antogkou/laravel-oauth2-client/actions)
[![License](https://img.shields.io/github/license/antogkou/laravel-oauth2-client)](LICENSE)
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
php artisan oauth2:generate-types
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
            'verify' => env('SERVICE1_VERIFY', true),
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
SERVICE1_VERIFY = true # Set to false to disable SSL verification
```

## Enabling IDE Autocompletion for Services

To enable IDE autocompletion for your OAuth2 service names in `OAuth2::for('...')`, this package provides a type generation command:

```bash
php artisan oauth2:generate-types
```

This command scans your `config/oauth2-client.php` and generates a PHP interface listing all configured service names. IDEs like PHPStorm and VSCode (with PHP Intelephense) will then offer autocompletion and static analysis for the available services.

**Workflow:**
1. Add or update your services in `config/oauth2-client.php` under the `services` key.
2. Run `php artisan oauth2:generate-types` to regenerate the types.
3. Enjoy autocompletion in your IDE for `OAuth2::for('your_service')`.

**Example:**
```php
// config/oauth2-client.php
'services' => [
    'service1' => [...],
    'service2' => [...],
],
```

After running the command, your IDE will suggest 'service1' and 'service2' in:
```php
OAuth2::for('service1')->get(...);
```

## Usage

### Basic API Calls

```php
use Antogkou\LaravelOAuth2Client\Facades\OAuth2;

// GET request
$response = OAuth2::for('service1')->get('https://api.service.com/data');

// POST request with JSON payload
$response = OAuth2::for('service1')->post('https://api.service.com/data', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]
]);

// POST form data
$response = OAuth2::for('service1')->post('https://api.service.com/data', [
    'form_params' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]
]);

// PUT request with JSON payload
$response = OAuth2::for('service1')->put('https://api.service.com/data/1', [
    'json' => [
        'name' => 'Updated Name'
    ]
]);

// PATCH request with JSON payload
$response = OAuth2::for('service1')->patch('https://api.service.com/data/1', [
    'json' => [
        'status' => 'active'
    ]
]);

// Request with custom headers
$response = OAuth2::for('service1')->post('https://api.service.com/data', [
    'json' => [
        'name' => 'John Doe'
    ],
    'headers' => [
        'X-Custom-Header' => 'value',
        'Accept' => 'application/json'
    ]
]);

// Get JSON response
$data = $response->json();
```

### Request Options

The package supports various request options that can be passed as an array:

#### JSON Payload

```php
$options = [
    'json' => [
        'key' => 'value',
        'nested' => ['data' => 'value']
    ]
];
```

#### Form Data

```php
$options = [
    'form_params' => [
        'field1' => 'value1',
        'field2' => 'value2'
    ]
];
```

#### Multipart/Form-Data (File Uploads)

```php
$options = [
    'multipart' => [
        [
            'name' => 'file',
            'contents' => fopen('path/to/file.pdf', 'r')
        ],
        [
            'name' => 'field',
            'contents' => 'value'
        ]
    ]
];
```

#### Custom Headers

```php
$options = [
    'headers' => [
        'Accept' => 'application/json',
        'X-Custom-Header' => 'value'
    ],
    'json' => [
        'data' => 'value'
    ]
];
```

### Available Methods

```php
OAuth2::for('service1')->get($url, $options = []);
OAuth2::for('service1')->post($url, $options = []);
OAuth2::for('service1')->put($url, $options = []);
OAuth2::for('service1')->patch($url, $options = []);
OAuth2::for('service1')->delete($url, $options = []);
OAuth2::for('service1')->request($method, $url, $options = []);
```

### Response Handling

```php
$response = OAuth2::for('service1')->post('https://api.service.com/data', [
    'json' => ['name' => 'John']
]);

if ($response->successful()) {
    $data = $response->json();
    $status = $response->status(); // 200, 201, etc.
    $headers = $response->headers();
} else {
    $errorData = $response->json();
    $statusCode = $response->status();
}
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

## Automatic JSON Error Responses

To make error handling seamless, you can configure your Laravel application's global exception handler to automatically return JSON responses for OAuth2 errors. This way, users of your API will always receive a structured JSON error response, and you don't need to manually catch exceptions in your controllers.

**How to set up:**

1. Open (or create) `app/Exceptions/Handler.php` in your Laravel application.
2. Add the following to your `render` method:

```php
use Antogkou\LaravelOAuth2Client\Exceptions\OAuth2Exception;

public function render($request, Throwable $exception)
{
    if ($exception instanceof OAuth2Exception) {
        return $exception->toResponse($request);
    }
    return parent::render($request, $exception);
}
```

**Debug Mode:**
- If you include an `X-Debug: 1` header or a `?debug=1` query parameter in your request, the error response will include additional debug information (stack trace, exception class).
- This is useful for development and debugging, but should be used with care in production.

**Example error response (with debug):**
```json
{
  "message": "Invalid token response format for service: myservice",
  "code": 500,
  "context": { ... },
  "exception": "Antogkou\\LaravelOAuth2Client\\Exceptions\\OAuth2Exception",
  "trace": [
    { "file": "/path/to/file.php", "line": 123, "function": "...", "class": "..." },
    ...
  ]
}
```

This setup is optional but highly recommended for API projects using this package.

## Testing

The package uses [Pest PHP](https://pestphp.com/) for testing. To run all tests:

```bash
composer test
```

To run only unit tests:
```bash
composer test:unit
```

To run static analysis:
```bash
composer test:types
```

To check code style:
```bash
composer lint
```

To test the artisan type generation command:
```bash
php artisan oauth2:generate-types
# Or run the dedicated test
pest tests/Feature/GenerateOAuth2TypesCommandTest.php
```

**Adding New Services for Testing:**
- Add your service to the `services` array in `config/oauth2-client.php`.
- Run the type generation command to update IDE support.

## Troubleshooting & FAQ

**Q: I added a new service but it doesn't autocomplete in my IDE.**
- Run `php artisan oauth2:generate-types` after updating your config.
- Restart your IDE if needed.

**Q: I get an exception about missing or invalid service configuration.**
- Ensure your service is correctly defined in `config/oauth2-client.php`.
- Check for typos in the service name.

**Q: How do I test error handling or simulate network/cache failures?**
- See the feature tests for examples of simulating HTTP and cache errors using Laravel's testing tools.

**Q: How do I contribute tests or features?**
- See the 'Contributing' section below. All new features should include tests.

## Contributing

Contributions welcome! Please follow:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request

## License

MIT License - See [LICENSE](LICENSE) for details.

## Support

For issues and feature requests,
please [create a GitHub issue](https://github.com/antogkou/laravel-oauth2-client/issues).
