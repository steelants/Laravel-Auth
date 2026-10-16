<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use SteelAnts\LaravelAuth\Support\Totp;
use SteelAnts\LaravelAuth\Tests\TestCase;

class FeatureCombinationsTest extends TestCase
{
    public function test_login_page_hides_register_link_when_registration_is_disabled()
    {
        $this->useAuthRoutes(['register' => false]);

        $this->get('/login')->assertOk()->assertDontSee('/register', false);
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_login_page_hides_reset_link_when_reset_is_disabled()
    {
        $this->useAuthRoutes(['reset' => false]);

        $this->get('/login')->assertOk()->assertDontSee('/password/reset', false);
        $this->get('/password/reset')->assertNotFound();
        $this->post('/password/email')->assertNotFound();
    }

    public function test_login_works_with_only_login_and_logout_enabled()
    {
        $this->useAuthRoutes(['register' => false, 'reset' => false]);
        $user = $this->createUser();

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('/register', false)
            ->assertDontSee('/password/reset', false);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/home');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_page_works_when_logout_is_disabled()
    {
        $this->useAuthRoutes(['logout' => false]);

        $this->get('/login')->assertOk();
        $this->post('/logout')->assertNotFound();
    }

    public function test_login_routes_can_be_disabled()
    {
        $this->useAuthRoutes(['login' => false]);

        $this->post('/login')->assertNotFound();
        $this->get('/register')->assertOk();
    }

    public function test_verify_routes_are_not_available_by_default()
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]))
            ->get('/dashboard')
            ->assertOk();

        $this->get('/email/verify')->assertNotFound();
    }

    public function test_totp_routes_are_not_available_by_default()
    {
        $this->actingAs($this->createUser(['totp_secret' => Totp::generateSecret()]))
            ->get('/dashboard')
            ->assertOk();

        $this->get('/two-factor')->assertNotFound();
    }

    public function test_registration_with_verification_requires_verifying_new_user()
    {
        $this->useAuthRoutes(['verify' => true]);

        $this->post('/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);
        $this->post('/login', ['email' => 'john@example.com', 'password' => 'secret-password']);

        $this->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_full_flow_with_verification_and_totp()
    {
        $this->useAuthRoutes(['verify' => true, 'totp' => true]);
        $user = $this->createUser(['email_verified_at' => null, 'totp_force' => true]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/home');
        $this->get('/home')->assertRedirect('/email/verify');

        auth()->user()->markEmailAsVerified();
        $this->get('/home')->assertRedirect('/two-factor');

        $this->get('/two-factor')->assertOk();
        $this->post('/two-factor', ['code' => Totp::generateCode(session('totp.secret'))])->assertRedirect('/home');

        $this->get('/dashboard')->assertOk();
    }
}
