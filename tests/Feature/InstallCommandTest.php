<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\File;
use SteelAnts\LaravelAuth\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private string $basePath;

    private string $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = $this->app->basePath();
        $this->basePath = sys_get_temp_dir() . '/laravel-auth-install-' . uniqid();

        File::ensureDirectoryExists($this->basePath . '/routes');
        File::put($this->basePath . '/routes/web.php', "<?php\n\n");

        $this->app->setBasePath($this->basePath);
    }

    protected function tearDown(): void
    {
        $this->app?->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->basePath);

        parent::tearDown();
    }

    public function test_it_publishes_scaffolding()
    {
        $this->artisan('install:auth', ['--force' => true])->assertSuccessful();

        $this->assertFileEquals(
            dirname(__DIR__, 2) . '/stubs/app/Http/Controllers/AuthController.stub',
            $this->basePath . '/app/Http/Controllers/AuthController.php'
        );

        foreach (['login', 'registration', 'reset', 'totp', 'verify'] as $view) {
            $this->assertFileExists($this->basePath . "/resources/views/auth/{$view}.blade.php");
        }

        $this->assertStringContainsString('Route::auth();', File::get($this->basePath . '/routes/web.php'));
    }

    public function test_it_appends_routes_only_once()
    {
        $this->artisan('install:auth', ['--force' => true])->assertSuccessful();
        $this->artisan('install:auth', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, substr_count(File::get($this->basePath . '/routes/web.php'), 'Route::auth();'));
    }

    public function test_it_overwrites_existing_files_with_force()
    {
        $controller = $this->basePath . '/app/Http/Controllers/AuthController.php';
        File::ensureDirectoryExists(dirname($controller));
        File::put($controller, 'custom');

        $this->artisan('install:auth', ['--force' => true])->assertSuccessful();

        $this->assertNotSame('custom', File::get($controller));
    }

    public function test_it_keeps_existing_files_when_not_confirmed()
    {
        $controller = $this->basePath . '/app/Http/Controllers/AuthController.php';
        File::ensureDirectoryExists(dirname($controller));
        File::put($controller, 'custom');

        $this->artisan('install:auth')
            ->expectsConfirmation('The [/app/Http/Controllers/AuthController.stub] view already exists. Do you want to replace it?', 'no')
            ->assertSuccessful();

        $this->assertSame('custom', File::get($controller));
    }
}
