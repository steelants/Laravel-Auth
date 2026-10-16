<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use SteelAnts\LaravelAuth\Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_reset_request_page_can_be_rendered()
    {
        $this->get('/password/reset')
            ->assertOk()
            ->assertViewIs('auth.reset')
            ->assertSee(route('password.email'), false);
    }

    public function test_reset_link_can_be_requested()
    {
        Notification::fake();
        $user = $this->createUser();

        $this->from('/password/reset')
            ->post('/password/email', ['email' => $user->email])
            ->assertRedirect('/password/reset')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_cannot_be_requested_for_unknown_email()
    {
        Notification::fake();

        $this->from('/password/reset')
            ->post('/password/email', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_link_requests_are_throttled()
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post('/password/email', ['email' => $user->email]);
        $this->from('/password/reset')
            ->post('/password/email', ['email' => $user->email])
            ->assertSessionHasErrors('email');

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_password_page_can_be_rendered_from_notification_link()
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post('/password/email', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            $this->get($url)
                ->assertOk()
                ->assertViewIs('auth.reset')
                ->assertViewHas('token', $notification->token)
                ->assertViewHas('email', $user->email)
                ->assertSee(route('password.update'), false);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Event::fake([PasswordReset::class]);
        $user = $this->createUser();
        $token = Password::createToken($user);

        $this->post('/password/reset', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/home')->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_password_cannot_be_reset_with_invalid_token()
    {
        $user = $this->createUser();

        $this->from('/password/reset/invalid-token')->post('/password/reset', [
            'token'                 => 'invalid-token',
            'email'                 => $user->email,
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/password/reset/invalid-token')->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_password_reset_requires_confirmed_password()
    {
        $user = $this->createUser();

        $this->post('/password/reset', [
            'token'                 => Password::createToken($user),
            'email'                 => $user->email,
            'password'              => 'new-password',
            'password_confirmation' => 'other-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
