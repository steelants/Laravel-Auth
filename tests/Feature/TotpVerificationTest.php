<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LogicException;
use SteelAnts\LaravelAuth\Support\Totp;
use SteelAnts\LaravelAuth\Tests\TestCase;

class TotpVerificationTest extends TestCase
{
    protected array $authRouteOptions = ['totp' => true];

    protected function createTotpUser(array $attributes = [])
    {
        return $this->createUser(array_merge([
            'totp_secret'       => Totp::generateSecret(),
            'totp_confirmed_at' => now(),
        ], $attributes));
    }

    public function test_user_without_totp_can_access_protected_page()
    {
        $this->actingAs($this->createUser())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_user_with_totp_is_redirected_to_prompt()
    {
        $this->actingAs($this->createTotpUser())
            ->get('/dashboard')
            ->assertRedirect('/two-factor');
    }

    public function test_user_with_totp_gets_forbidden_for_json_requests()
    {
        $this->actingAs($this->createTotpUser())
            ->getJson('/dashboard')
            ->assertForbidden();
    }

    public function test_forced_user_without_totp_is_redirected_to_prompt()
    {
        $this->actingAs($this->createUser(['totp_force' => true]))
            ->get('/dashboard')
            ->assertRedirect('/two-factor');
    }

    public function test_user_with_totp_is_redirected_to_prompt_after_login()
    {
        $user = $this->createTotpUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/home');

        $this->get('/home')->assertRedirect('/two-factor');
    }

    public function test_prompt_can_be_rendered_for_user_with_totp()
    {
        $this->actingAs($this->createTotpUser())
            ->get('/two-factor')
            ->assertOk()
            ->assertViewIs('auth.totp')
            ->assertViewHas('hasTotp', true)
            ->assertDontSee('TOTP QR');
    }

    public function test_prompt_shows_setup_qr_code_for_user_without_totp()
    {
        $response = $this->actingAs($this->createUser(['totp_force' => true]))
            ->get('/two-factor')
            ->assertOk()
            ->assertViewHas('hasTotp', false)
            ->assertSee('data:image/png;base64,', false);

        $secret = session('totp.secret');

        $this->assertNotEmpty($secret);
        $response->assertViewHas('secret', $secret);
        $response->assertViewHas('otpauth', fn ($url) => str_contains($url, 'secret=' . $secret));
    }

    public function test_prompt_keeps_setup_secret_between_reloads()
    {
        $this->actingAs($this->createUser(['totp_force' => true]))->get('/two-factor');
        $secret = session('totp.secret');

        $this->get('/two-factor')->assertViewHas('secret', $secret);
    }

    public function test_guest_is_redirected_to_login_from_prompt()
    {
        $this->get('/two-factor')->assertRedirect('/login');
    }

    public function test_user_can_pass_totp_with_valid_code()
    {
        $user = $this->createTotpUser();

        $this->actingAs($user)
            ->post('/two-factor', ['code' => Totp::generateCode($user->totp_secret)])
            ->assertRedirect('/home')
            ->assertSessionHas('totp_passed', true);

        $this->get('/dashboard')->assertOk();
    }

    public function test_user_is_redirected_to_intended_page_after_totp()
    {
        $user = $this->createTotpUser();

        $this->get('/account')->assertRedirect('/login');
        $this->get('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/account');
        $this->get('/account')->assertRedirect('/two-factor');

        $this->post('/two-factor', ['code' => Totp::generateCode($user->totp_secret)])
            ->assertRedirect('/account');
    }

    public function test_user_cannot_pass_totp_with_invalid_code()
    {
        $user = $this->createTotpUser();
        $code = Totp::generateCode($user->totp_secret);
        $wrong = str_pad((string) (((int) $code + 500000) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->actingAs($user)
            ->from('/two-factor')
            ->post('/two-factor', ['code' => $wrong])
            ->assertRedirect('/two-factor')
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('totp_passed');

        $this->get('/dashboard')->assertRedirect('/two-factor');
    }

    public function test_totp_code_must_have_six_digits()
    {
        $this->actingAs($this->createTotpUser())
            ->from('/two-factor')
            ->post('/two-factor', ['code' => '12ab'])
            ->assertSessionHasErrors('code');
    }

    public function test_user_can_enable_totp()
    {
        $user = $this->createUser(['totp_force' => true]);

        $this->actingAs($user)->get('/two-factor');
        $secret = session('totp.secret');

        $this->post('/two-factor', ['code' => Totp::generateCode($secret)])
            ->assertRedirect('/home')
            ->assertSessionMissing('totp.secret');

        $user->refresh();
        $this->assertSame($secret, $user->totp_secret);
        $this->assertNotNull($user->totp_confirmed_at);
        $this->get('/dashboard')->assertOk();
    }

    public function test_unconfirmed_totp_is_confirmed_on_first_valid_code()
    {
        $user = $this->createTotpUser(['totp_confirmed_at' => null]);

        $this->actingAs($user)
            ->post('/two-factor', ['code' => Totp::generateCode($user->totp_secret)])
            ->assertRedirect('/home');

        $this->assertNotNull($user->fresh()->totp_confirmed_at);
    }

    public function test_user_with_pending_totp_can_logout()
    {
        $this->actingAs($this->createTotpUser())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_totp_middleware_requires_totp_routes()
    {
        Route::middleware(['web', 'verified.totp'])->get('/totp-protected', fn () => 'ok');
        Route::getRoutes()->refreshNameLookups();

        $this->app['router']->getRoutes()->getByName('totp.prompt')->name('renamed.prompt');
        Route::getRoutes()->refreshNameLookups();

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);

        $this->get('/totp-protected');
    }
}
