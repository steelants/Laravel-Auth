<?php

namespace SteelAnts\LaravelAuth\Tests;

use App\Models\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Orchestra;
use SteelAnts\LaravelAuth\AuthServiceProvider;

#[WithMigration]
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Options passed to Route::auth() in defineWebRoutes().
     */
    protected array $authRouteOptions = [];

    protected function getPackageProviders($app): array
    {
        return [
            AuthServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config) {
            $config->set('database.default', 'testing');
            $config->set('auth.providers.users.model', User::class);
            $config->set('app.name', 'Laravel Auth');
        });

        // Render the package stubs directly, so the tests cover the views users get after install:auth.
        View::addExtension('blade.stub', 'blade');
        View::addLocation(dirname(__DIR__) . '/stubs/resources/views');
        View::addLocation(__DIR__ . '/Fixtures/resources/views');

        // As in a real application, the HTTP kernel must exist before routes are registered, otherwise its
        // constructor would sync middleware groups to the router and drop middleware pushed by Route::auth().
        $app->make(HttpKernel::class);
    }

    protected function defineWebRoutes($router): void
    {
        $router->get('/', fn () => 'welcome');
        $router->get('/home', fn () => 'home')->name('home');
        $router->get('/dashboard', fn () => 'dashboard')->middleware('auth')->name('dashboard');
        $router->get('/account', fn () => 'account')->middleware('auth')->name('account');

        $router->auth($this->authRouteOptions);
    }

    /**
     * Re-register all routes with different Route::auth() options.
     * Use only from test classes with default $authRouteOptions, because middleware pushed to the web group stays.
     */
    protected function useAuthRoutes(array $options): void
    {
        $this->authRouteOptions = $options;

        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection());
        $router->middleware('web')->group(fn ($router) => $this->defineWebRoutes($router));
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();

        $this->app['url']->setRoutes($router->getRoutes());
    }

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }
}
