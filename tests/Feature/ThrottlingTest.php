<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use SteelAnts\LaravelAuth\Tests\TestCase;

class ThrottlingTest extends TestCase
{
    private function failLogin(string $email, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->from('/login')->post('/login', ['email' => $email, 'password' => 'wrong-password']);
        }
    }

    public function test_login_is_throttled_after_five_failed_attempts()
    {
        $user = $this->createUser();

        $this->failLogin($user->email, 5);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', $user->email);

        $this->assertGuest();
    }

    public function test_login_is_not_throttled_before_limit_is_reached()
    {
        $user = $this->createUser();

        $this->failLogin($user->email, 4);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_resets_failed_attempts()
    {
        $user = $this->createUser();

        $this->failLogin($user->email, 4);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/home');
        $this->post('/logout');

        $this->failLogin($user->email, 4);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_throttle_is_per_email()
    {
        $user = $this->createUser();
        $other = $this->createUser();

        $this->failLogin($user->email, 5);

        $this->post('/login', ['email' => $other->email, 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($other);
    }

    public function test_login_throttle_ignores_email_case()
    {
        $user = $this->createUser();

        $this->failLogin(strtoupper($user->email), 5);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_throttle_is_released_after_one_minute()
    {
        $user = $this->createUser();

        $this->failLogin($user->email, 5);
        $this->travel(61)->seconds();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_link_request_is_throttled_by_password_broker()
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post('/password/email', ['email' => $user->email])->assertSessionHasNoErrors();
        $this->post('/password/email', ['email' => $user->email])->assertSessionHasErrors('email');

        $this->travel(61)->seconds();

        $this->post('/password/email', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, ResetPassword::class, 2);
    }

    public function test_reset_password_form_is_throttled()
    {
        for ($i = 0; $i < 6; $i++) {
            $this->get('/password/reset/some-token')->assertOk();
        }

        $this->get('/password/reset/some-token')->assertTooManyRequests();
    }

    public function test_reset_password_submit_is_throttled()
    {
        $user = $this->createUser();
        $payload = [
            'token'                 => 'invalid-token',
            'email'                 => $user->email,
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        for ($i = 0; $i < 6; $i++) {
            $this->post('/password/reset', $payload)->assertRedirect();
        }

        $this->post('/password/reset', $payload)->assertTooManyRequests();
    }

    public function test_throttle_is_released_after_one_minute()
    {
        for ($i = 0; $i < 7; $i++) {
            $this->get('/password/reset/some-token');
        }
        $this->get('/password/reset/some-token')->assertTooManyRequests();

        $this->travel(61)->seconds();

        $this->get('/password/reset/some-token')->assertOk();
    }

    public function test_totp_verification_is_throttled()
    {
        $this->useAuthRoutes(['totp' => true]);
        $user = $this->createUser(['totp_secret' => \SteelAnts\LaravelAuth\Support\Totp::generateSecret()]);

        $this->actingAs($user);
        for ($i = 0; $i < 10; $i++) {
            $this->from('/two-factor')->post('/two-factor', ['code' => '000000']);
        }

        $this->post('/two-factor', ['code' => '000000'])->assertTooManyRequests();
    }

    public function test_verification_email_resend_is_throttled()
    {
        Notification::fake();
        $this->useAuthRoutes(['verify' => true]);
        $this->actingAs($this->createUser(['email_verified_at' => null]));

        for ($i = 0; $i < 6; $i++) {
            $this->from('/email/verify')->post('/email/resend');
        }

        $this->post('/email/resend')->assertTooManyRequests();
    }

    public function test_email_verification_link_is_throttled()
    {
        $this->useAuthRoutes(['verify' => true]);
        $user = $this->createUser(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id'   => $user->getKey(),
            'hash' => sha1('wrong@example.com'),
        ]);

        $this->actingAs($user);
        for ($i = 0; $i < 6; $i++) {
            $this->get($url);
        }

        $this->get($url)->assertTooManyRequests();
    }
}
