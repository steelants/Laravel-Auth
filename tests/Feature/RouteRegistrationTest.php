<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use SteelAnts\LaravelAuth\Middleware\EnsureEmailIsVerified;
use SteelAnts\LaravelAuth\Middleware\EnsureTotpVerified;
use SteelAnts\LaravelAuth\Tests\TestCase;

class RouteRegistrationTest extends TestCase
{
    private const FEATURE_ROUTES = [
        'login'    => ['login', 'login.submit'],
        'logout'   => ['logout'],
        'register' => ['register', 'register.submit'],
        'reset'    => ['password', 'password.email', 'password.reset', 'password.update'],
        'verify'   => ['verification.notice', 'verification.verify', 'verification.resend'],
        'totp'     => ['totp.prompt', 'totp.verify'],
    ];

    private const DEFAULTS = [
        'login'    => true,
        'logout'   => true,
        'register' => true,
        'reset'    => true,
        'verify'   => false,
        'totp'     => false,
    ];

    /**
     * Every combination of enabled / disabled features (2^6 = 64 cases).
     */
    public static function featureCombinations(): iterable
    {
        $features = array_keys(self::FEATURE_ROUTES);

        for ($mask = 0; $mask < 2 ** count($features); $mask++) {
            $options = [];
            foreach ($features as $i => $feature) {
                $options[$feature] = (bool) ($mask & (1 << $i));
            }

            $label = implode(',', array_keys(array_filter($options))) ?: 'none';
            yield $label => [$options];
        }
    }

    private function webGroup(): array
    {
        return $this->app['router']->getMiddlewareGroups()['web'] ?? [];
    }

    public function test_auth_macro_is_registered()
    {
        $this->assertTrue(Route::hasMacro('auth'));
    }

    public function test_middleware_aliases_are_registered()
    {
        $aliases = $this->app['router']->getMiddleware();

        $this->assertSame(EnsureEmailIsVerified::class, $aliases['verified']);
        $this->assertSame(EnsureTotpVerified::class, $aliases['verified.totp']);
    }

    public function test_package_migration_adds_totp_columns()
    {
        $this->assertTrue(Schema::hasColumns('users', ['totp_secret', 'totp_confirmed_at', 'totp_force']));
    }

    public function test_default_routes_are_registered()
    {
        foreach (self::DEFAULTS as $feature => $enabled) {
            foreach (self::FEATURE_ROUTES[$feature] as $name) {
                $this->assertSame($enabled, Route::has($name), "Route [{$name}] registration mismatch.");
            }
        }

        $this->assertNotContains(EnsureEmailIsVerified::class, $this->webGroup());
        $this->assertNotContains(EnsureTotpVerified::class, $this->webGroup());
    }

    public function test_routes_have_expected_uris_methods_and_actions()
    {
        $this->useAuthRoutes(['verify' => true, 'totp' => true]);

        $expected = [
            ['GET', 'login', 'login', 'login'],
            ['POST', 'login', 'login.submit', 'loginPost'],
            ['POST', 'logout', 'logout', 'logout'],
            ['GET', 'logout', null, 'logout'],
            ['GET', 'register', 'register', 'register'],
            ['POST', 'register', 'register.submit', 'registerPost'],
            ['GET', 'password/reset', 'password', 'reset'],
            ['POST', 'password/email', 'password.email', 'resetPost'],
            ['GET', 'password/reset/{token}', 'password.reset', 'resetToken'],
            ['POST', 'password/reset', 'password.update', 'resetPasswordSubmit'],
            ['GET', 'email/verify', 'verification.notice', 'verificationNotice'],
            ['GET', 'email/verify/{id}/{hash}', 'verification.verify', 'verifyEmail'],
            ['POST', 'email/resend', 'verification.resend', 'resendVerification'],
            ['GET', 'two-factor', 'totp.prompt', 'totpPrompt'],
            ['POST', 'two-factor', 'totp.verify', 'totpVerify'],
        ];

        $routes = $this->app['router']->getRoutes();

        foreach ($expected as [$method, $uri, $name, $action]) {
            $route = $routes->match(\Illuminate\Http\Request::create('/' . str_replace(['{token}', '{id}', '{hash}'], ['t', '1', 'h'], $uri), $method));

            $this->assertSame($uri, $route->uri(), "{$method} /{$uri}");
            $this->assertSame($name, $route->getName(), "{$method} /{$uri}");
            $this->assertSame('App\Http\Controllers\AuthController@' . $action, $route->getActionName(), "{$method} /{$uri}");
        }
    }

    public function test_routes_have_expected_middleware()
    {
        $this->useAuthRoutes(['verify' => true, 'totp' => true]);

        $middleware = fn (string $name) => Route::getRoutes()->getByName($name)->gatherMiddleware();
        $excluded = fn (string $name) => Route::getRoutes()->getByName($name)->excludedMiddleware();

        $this->assertContains('throttle:6,1', $middleware('password.reset'));
        $this->assertContains('throttle:6,1', $middleware('password.update'));

        $this->assertContains('auth', $middleware('verification.notice'));
        $this->assertContains('auth', $middleware('verification.verify'));
        $this->assertContains('signed', $middleware('verification.verify'));
        $this->assertContains('throttle:6,1', $middleware('verification.verify'));
        $this->assertContains('auth', $middleware('verification.resend'));
        $this->assertContains('throttle:6,1', $middleware('verification.resend'));

        $this->assertContains('auth', $middleware('totp.prompt'));
        $this->assertContains('auth', $middleware('totp.verify'));
        $this->assertContains('throttle:10,1', $middleware('totp.verify'));

        foreach (['verification.notice', 'verification.verify', 'verification.resend'] as $name) {
            $this->assertContains(EnsureEmailIsVerified::class, $excluded($name));
        }
        foreach (['totp.prompt', 'totp.verify'] as $name) {
            $this->assertContains(EnsureTotpVerified::class, $excluded($name));
        }
        $this->assertContains(EnsureEmailIsVerified::class, $excluded('logout'));
        $this->assertContains(EnsureTotpVerified::class, $excluded('logout'));
    }

    #[DataProvider('featureCombinations')]
    public function test_feature_combination_registers_matching_routes(array $options)
    {
        $this->useAuthRoutes($options);

        foreach (self::FEATURE_ROUTES as $feature => $names) {
            foreach ($names as $name) {
                $this->assertSame($options[$feature], Route::has($name), "Route [{$name}] registration mismatch.");
            }
        }

        $this->assertSame($options['verify'], in_array(EnsureEmailIsVerified::class, $this->webGroup(), true));
        $this->assertSame($options['totp'], in_array(EnsureTotpVerified::class, $this->webGroup(), true));
    }

    public function test_options_can_be_partially_specified()
    {
        $this->useAuthRoutes(['register' => false]);

        $this->assertFalse(Route::has('register'));
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('password'));
        $this->assertFalse(Route::has('verification.notice'));
    }
}
