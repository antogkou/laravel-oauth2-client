# Laravel OAuth2 Client Package

Laravel OAuth2 Client is a Composer package that provides OAuth2 Client Credentials flow integration for Laravel applications with automatic token management, caching, and error handling.

Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.

## Working Effectively

### Dependencies and Installation
- Install PHP dependencies: `composer install --no-interaction` -- NEVER CANCEL, takes 5-10 minutes with GitHub auth issues
- **CRITICAL**: Composer installation often fails with GitHub authentication errors due to rate limiting
- **FALLBACK**: Use `composer install --no-dev --no-interaction --prefer-dist` to install minimal dependencies (takes 2-3 minutes)
- **TIMING**: Full installation with dev dependencies: 5-10 minutes, minimal installation: 2-3 minutes 
- Authentication failures are expected and installation will continue using source downloads automatically

### Build and Test Commands  
- **IMPORTANT**: Test matrix scripts mentioned in .junie/guidelines.md (test-matrix.sh, test-all-matrix.sh) do NOT exist in the repository
- Composer scripts require dev dependencies to be fully installed, which may fail due to GitHub authentication issues
- Available composer scripts (when dev dependencies are installed):
  - `composer test:refactor` -- Rector dry run analysis 
  - `composer test:lint` -- Laravel Pint code style check
  - `composer test:types` -- PHPStan static analysis (takes ~30 seconds, NEVER CANCEL)
  - `composer test:unit` -- Pest PHP unit tests with coverage (takes ~45 seconds, NEVER CANCEL)
  - `composer test` -- runs all above commands sequentially (takes ~2 minutes total, NEVER CANCEL)
  - `composer lint` -- Laravel Pint code style fix
  - `composer refactor` -- Rector code refactoring

### Validation and Testing
- ALWAYS run `php -l src/*.php` to check syntax before making changes (< 1 second per file)
- ALWAYS validate autoload works: `php -r "require 'vendor/autoload.php'; echo 'OK';"` (when vendor is available)
- Due to authentication issues, full test suite may not be available in all environments
- **MINIMUM VALIDATION**: Check PHP syntax of all modified files with `find src/ -name "*.php" -exec php -l {} \;`
- When dev dependencies are available, ALWAYS run `composer test:lint` and `composer test:types` before submitting changes
- **NEVER CANCEL** any composer install, test, or analysis commands - they may take 5-10 minutes but will complete

### Installation Troubleshooting
**Issue**: `composer install` fails with GitHub authentication errors
**Solution**: Use `composer install --no-dev --no-interaction --prefer-dist` to install core dependencies only

**Issue**: `pest: not found` or similar command not found errors  
**Solution**: Dev dependencies failed to install. Use syntax checking and basic validation instead

**Issue**: `Could not authenticate against github.com`
**Solution**: Expected behavior due to rate limiting. Installation will continue using source downloads (slower but works)

## Project Structure and Navigation
- `src/` - Main source code
  - `OAuth2Client.php` - Core OAuth2 client class with token management
  - `OAuth2ClientServiceProvider.php` - Laravel service provider for package registration  
  - `Facades/OAuth2.php` - Laravel facade for convenient access
  - `Console/GenerateOAuth2TypesCommand.php` - Artisan command for IDE type generation
  - `OAuth2ServiceTypeGenerator.php` - Service for generating OAuth2 service types
  - `Exceptions/` - Custom exception classes
- `config/oauth2-client.php` - Package configuration with service definitions
- `tests/` - Test suite (Pest PHP)
  - `Feature/` - Feature tests for integration scenarios
  - `Unit/` - Unit tests for individual components
  - `TestCase.php` - Base test class extending Orchestra Testbench
- `.github/workflows/tests.yml` - CI pipeline configuration
- `composer.json` - Package dependencies and scripts
- `phpstan.neon` - Static analysis configuration  
- `pint.json` - Code style configuration
- `rector.php` - Code refactoring rules

## Key Functionality to Test When Making Changes
1. **OAuth2 Client Core**: Test service configuration and token retrieval
2. **Laravel Integration**: Verify service provider registration and facade access
3. **Artisan Command**: Test `php artisan oauth2:generate-types` for type generation
4. **Configuration**: Validate service definitions in config/oauth2-client.php

### Manual Testing Scenarios  
When full test suite is not available due to installation issues:
1. **Syntax Validation**: `find src/ -name "*.php" -exec php -l {} \;` for all files
2. **Autoload Test**: `php -r "require 'vendor/autoload.php'; echo 'OK';"` to verify composer autoload
3. **Configuration Syntax**: `php -l config/oauth2-client.php` (syntax only - requires Laravel for full parsing)
4. **Basic Class Loading**: `php -r "require 'vendor/autoload.php'; echo class_exists('Antogkou\\LaravelOAuth2Client\\OAuth2Client') ? 'OK' : 'FAIL';"` 

### Quick Validation Commands (Always Run These)
```bash
# Check all PHP syntax (takes ~1 second)
find src/ -name "*.php" -exec php -l {} \;

# Verify autoload works (when vendor available)  
php -r "require 'vendor/autoload.php'; echo 'Autoload: OK\n';"

# Check config syntax only (config requires Laravel environment for full parsing)
php -l config/oauth2-client.php
```

## Development Workflow
1. Make minimal code changes following existing patterns
2. Run syntax validation: `php -l` on modified files  
3. If dev dependencies available: run `composer test:lint` and `composer test:types`
4. Test the OAuth2 type generation command if modifying configuration
5. Verify no breaking changes to public API

## Important Notes
- This is a **Laravel package**, not a standalone application
- No traditional "build" process - it's a Composer library
- The package supports PHP 8.2+ and Laravel 11.0+ 
- Uses strict typing (`declare(strict_types=1);`) throughout
- Follows PSR-12 coding standards
- OAuth2 tokens are cached using Laravel's cache system
- All sensitive data is automatically redacted from logs

## Common File Patterns to Recognize
- All PHP files start with `<?php declare(strict_types=1);`
- Classes are marked `final` where appropriate  
- Methods include type hints and return types
- PHPDoc comments document complex methods
- Configuration uses environment variables with defaults
- Tests use Pest PHP syntax (not PHPUnit)

## Timing Expectations (NEVER CANCEL THESE COMMANDS)
- **Composer install (full with dev)**: 5-10 minutes, NEVER CANCEL - GitHub auth issues are normal
- **Composer install (--no-dev)**: 2-3 minutes, NEVER CANCEL
- **Static analysis (phpstan)**: 30-60 seconds, NEVER CANCEL - Set timeout to 120+ seconds
- **Unit tests (pest)**: 45-90 seconds, NEVER CANCEL - Set timeout to 180+ seconds  
- **Full test suite**: 2-3 minutes total, NEVER CANCEL - Set timeout to 300+ seconds
- **Code style check**: 10-30 seconds
- **PHP syntax validation**: < 1 second per file
- **Autoload validation**: < 1 second