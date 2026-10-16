<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\URL;
use SteelAnts\LaravelAuth\Support\Totp;
use SteelAnts\LaravelAuth\Tests\TestCase;

class EmailVerificationWithTotpTest extends TestCase
{
    protected array $authRouteOptions = ['verify' => true, 'totp' => true];

    public function test_email_verification_takes_precedence_over_totp()
    {
        $this->actingAs($this->createUser([
            'email_verified_at' => null,
            'totp_secret'       => Totp::generateSecret(),
        ]))->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_unverified_user_cannot_open_totp_prompt()
    {
        $this->actingAs($this->createUser([
            'email_verified_at' => null,
            'totp_force'        => true,
        ]))->get('/two-factor')->assertRedirect('/email/verify');
    }

    public function test_verified_user_with_totp_is_redirected_to_prompt()
    {
        $this->actingAs($this->createUser(['totp_secret' => Totp::generateSecret()]))
            ->get('/dashboard')
            ->assertRedirect('/two-factor');
    }

    public function test_user_is_redirected_to_intended_page_after_login_email_verification_and_totp()
    {
        $user = $this->createUser([
            'email_verified_at' => null,
            'totp_secret'       => Totp::generateSecret(),
            'totp_confirmed_at' => now(),
        ]);

        $this->get('/account')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/account');
        $this->get('/account')->assertRedirect('/email/verify');

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id'   => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->get($verificationUrl)->assertRedirect('/account');
        $this->get('/account')->assertRedirect('/two-factor');

        $this->post('/two-factor', ['code' => Totp::generateCode($user->totp_secret)])
            ->assertRedirect('/account');
        $this->get('/account')->assertOk()->assertSee('account');
    }
}
