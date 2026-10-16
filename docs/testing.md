# Testing

SteelAnts Laravel-Auth is tested with PHPUnit and [Orchestra Testbench](https://packages.tools/testbench), which boots a minimal Laravel application around the package. No host application is required.


## Install Dependencies

```bash
composer install
```


## Running Tests

```bash
composer test
```

or directly:

```bash
./vendor/bin/phpunit
```

Run a single file or test:

```bash
./vendor/bin/phpunit tests/Feature/AuthenticationTest.php
./vendor/bin/phpunit --filter test_user_can_login
```


## Test Structure

```
tests/
|-- TestCase.php                       Testbench base class (provider, sqlite, routes, views)
|-- Fixtures/
|   |-- app/Http/Controllers/          AuthController with all feature traits, base Controller
|   |-- app/Models/User.php            User model implementing MustVerifyEmail
|   `-- resources/views/components/    <x-layout-auth> used by the stub views
|-- Unit/
|   `-- TotpTest.php                   RFC 6238 vectors, secrets, otpauth URL, QR code
`-- Feature/
    |-- AuthenticationTest.php         Login, logout, redirect to login and back after interrupted login
    |-- RegistrationTest.php
    |-- PasswordResetTest.php
    |-- EmailVerificationTest.php      Route::auth(['verify' => true])
    |-- TotpVerificationTest.php       Route::auth(['totp' => true])
    |-- EmailVerificationWithTotpTest.php
    |-- FeatureCombinationsTest.php    HTTP behaviour with features enabled / disabled
    |-- RouteRegistrationTest.php      Routes, middleware and all 64 option combinations
    |-- ThrottlingTest.php             Rate limits of login, reset, TOTP and verification
    `-- InstallCommandTest.php         php artisan install:auth
```

The test environment:

- uses an in-memory SQLite database with Laravel's default migrations and the package TOTP migration,
- renders the real stub views from `stubs/resources/views` (`.blade.stub` is registered as a Blade extension),
- registers `/`, `/home`, `/dashboard` and `/account` routes plus `Route::auth($this->authRouteOptions)`.

Enable features for a whole test class through `$authRouteOptions`:

```php
class TotpVerificationTest extends TestCase
{
    protected array $authRouteOptions = ['totp' => true];
}
```

or re-register routes inside a single test:

```php
$this->useAuthRoutes(['register' => false, 'reset' => false]);
```


## Continuous Integration

GitHub Actions (`.github/workflows/tests.yml`) runs the suite on every pull request (a newer push cancels the running check), once for every supported Laravel version on the highest PHP version that version supports:

| Laravel | Testbench | PHP |
|---------|-----------|-----|
| 11.x    | 9.x       | 8.4 |
| 12.x    | 10.x      | 8.5 |
| 13.x    | 11.x      | 8.5 |

Laravel 11 is out of security support and every release has an open security advisory, so its job disables Composer's advisory blocking (`policy.advisories.block`).

To reproduce a CI job locally:

```bash
composer require "laravel/framework:12.*" "orchestra/testbench:10.*" --dev --no-update
composer update --prefer-stable
./vendor/bin/phpunit
```

Do not commit the changes made to `composer.json` by this command.


## Before Creating a Pull Request

```bash
composer test
composer lint
```

All tests should pass before merging changes.


## Next Steps

Continue with:

- [Development](development.md)
- [Usage](usage.md)
- [Configuration](configuration.md)
