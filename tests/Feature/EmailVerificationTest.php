<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use SteelAnts\LaravelAuth\Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    protected array $authRouteOptions = ['verify' => true];

    protected function verificationUrl($user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id'   => $user->getKey(),
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_unverified_user_is_redirected_to_verification_notice()
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]))
            ->get('/dashboard')
            ->assertRedirect('/email/verify');
    }

    public function test_unverified_user_gets_forbidden_for_json_requests()
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]))
            ->getJson('/dashboard')
            ->assertForbidden();
    }

    public function test_verified_user_can_access_protected_page()
    {
        $this->actingAs($this->createUser())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_unverified_user_is_redirected_to_verification_notice_after_login()
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/home');

        $this->get('/home')->assertRedirect('/email/verify');
    }

    public function test_verification_notice_can_be_rendered()
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]))
            ->get('/email/verify')
            ->assertOk()
            ->assertViewIs('auth.verify')
            ->assertSee(route('verification.resend'), false);
    }

    public function test_verified_user_is_redirected_away_from_verification_notice()
    {
        $this->actingAs($this->createUser())
            ->get('/email/verify')
            ->assertRedirect('/home');
    }

    public function test_guest_is_redirected_to_login_from_verification_notice()
    {
        $this->get('/email/verify')->assertRedirect('/login');
    }

    public function test_email_can_be_verified()
    {
        Event::fake([Verified::class]);
        $user = $this->createUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect('/home');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_user_is_redirected_to_intended_page_after_verification()
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $this->get('/account')->assertRedirect('/login');
        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect('/account');
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get($this->verificationUrl($user, sha1('wrong@example.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_unsigned_url()
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->getKey(), 'hash' => sha1($user->email)]))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_is_redirected_from_verification_link()
    {
        Event::fake([Verified::class]);
        $user = $this->createUser();

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect('/home');

        Event::assertNotDispatched(Verified::class);
    }

    public function test_verification_email_can_be_resent()
    {
        Notification::fake();
        $user = $this->createUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/resend')
            ->assertRedirect('/email/verify')
            ->assertSessionHas('resent', true);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_email_is_not_resent_to_verified_user()
    {
        Notification::fake();
        $user = $this->createUser();

        $this->actingAs($user)->post('/email/resend')->assertRedirect('/home');

        Notification::assertNothingSent();
    }

    public function test_unverified_user_can_logout()
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]))
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_guest_can_access_login_page()
    {
        $this->get('/login')->assertOk();
    }
}
