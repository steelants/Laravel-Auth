<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

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
}
