# Laravel OAuth2 Client - Project Guidelines

## Project Overview
Laravel OAuth2 Client is a PHP package for Laravel that implements the OAuth2 Client Credentials flow with automatic token management. The package provides a clean, fluent interface for making authenticated API requests to OAuth2-protected services.

### Key Features
- Automatic token acquisition and refresh
- Support for multiple OAuth2 providers
- Token caching using Laravel's Cache system
- Structured logging with sensitive data redaction
- SSL verification options
- Comprehensive error handling

## Project Structure
- `src/` - Source code
  - `OAuth2Client.php` - Core class handling OAuth2 authentication and requests
  - `OAuth2ClientServiceProvider.php` - Laravel service provider for package integration
  - `Facades/OAuth2.php` - Facade for convenient access to the client
  - `Console/` - Artisan commands
  - `Exceptions/` - Custom exceptions
- `config/` - Configuration files
- `tests/` - Test suite
  - `Feature/` - Feature tests
  - `Unit/` - Unit tests
- `vendor/` - Dependencies (managed by Composer)

## Testing Instructions
The package uses Pest PHP for testing. To run tests:

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run static analysis
composer test:types

# Check code style
composer test:lint

# Check for potential refactoring opportunities
composer test:refactor
```

### Testing with Different PHP and Laravel Versions
The package includes scripts to test compatibility with different PHP and Laravel versions:

```bash
# Test with current PHP version and Laravel 11.*
./test-matrix.sh

# Test with specific PHP and Laravel versions
./test-matrix.sh 8.3 11.*
./test-matrix.sh 8.4 12.*

# Test all supported combinations
./test-all-matrix.sh
```

## Build Instructions
This is a Composer package, so there's no traditional build process. However, before submitting changes:

1. Run all tests to ensure they pass
2. Run static analysis to check for type errors
3. Run code style checks to ensure consistent formatting
4. Verify compatibility with supported PHP and Laravel versions

## Code Style Guidelines
The package follows PSR-12 coding standards and uses Laravel Pint for code style enforcement. Key guidelines:

1. Use strict typing (`declare(strict_types=1);`)
2. Use type hints for parameters and return types
3. Document methods with PHPDoc comments
4. Follow Laravel's coding style
5. Use final classes where appropriate
6. Properly handle exceptions and provide context

To check and fix code style:
```bash
# Check code style
composer test:lint

# Fix code style issues
composer lint
```

## Development Workflow
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and static analysis
5. Submit a pull request

## Security Considerations
- Never commit client secrets - Always use environment variables
- The package automatically redacts sensitive data from logs
- Tokens are stored in Laravel's cache, not in permanent storage
- SSL verification is enabled by default but can be disabled if needed

## Additional Resources
- [GitHub Repository](https://github.com/antogkou/laravel-oauth2-client)
- [Packagist Page](https://packagist.org/packages/antogkou/laravel-oauth2-client)
