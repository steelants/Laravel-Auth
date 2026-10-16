<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use SteelAnts\LaravelAuth\Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_can_be_rendered()
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee(route('login.submit'), false)
            ->assertSee(route('register'), false)
            ->assertSee(route('password'), false);
    }

    public function test_user_can_login()
    {
        $user = $this->createUser();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password()
    {
        $user = $this->createUser();

        $this->from('/login')->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_user_cannot_login_with_unknown_email()
    {
        $this->from('/login')->post('/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_login_requires_valid_email_and_password()
    {
        $this->from('/login')->post('/login', [
            'email'    => 'not-an-email',
            'password' => '',
        ])->assertRedirect('/login')->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_user_can_be_remembered()
    {
        $user = $this->createUser();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertRedirect('/home');
        $this->assertNotNull($user->fresh()->remember_token);
        $this->assertNotEmpty(array_filter(
            $response->headers->getCookies(),
            fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_')
        ));
    }

    public function test_session_is_regenerated_after_login()
    {
        $user = $this->createUser();

        $this->get('/login');
        $sessionId = session()->getId();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotSame($sessionId, session()->getId());
    }

    public function test_authenticated_user_cannot_see_login_page()
    {
        $this->actingAs($this->createUser())
            ->get('/login')
            ->assertRedirect();
    }

    public function test_guest_is_redirected_to_login_from_protected_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_is_redirected_back_to_protected_page_after_interrupted_login()
    {
        $user = $this->createUser();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('dashboard');
    }

    public function test_intended_url_is_used_only_once()
    {
        $user = $this->createUser();

        $this->get('/dashboard');
        $this->get('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->post('/logout');
        $this->get('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/home');
    }

    public function test_user_is_redirected_back_after_failed_and_then_successful_login()
    {
        $user = $this->createUser();

        $this->get('/dashboard');
        $this->get('/login');

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login');
        $this->get('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
    }

    public function test_user_is_not_redirected_back_to_auth_pages_after_login()
    {
        $user = $this->createUser();

        foreach (['/register', '/password/reset'] as $authPage) {
            $this->get($authPage);
            $this->get('/login');

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/home');

            $this->post('/logout');
        }
    }

    public function test_login_page_works_with_referer_to_unknown_page()
    {
        $this->withHeader('referer', 'http://localhost/does-not-exist')
            ->get('/login')
            ->assertOk();
    }

    public function test_user_is_not_redirected_to_external_referer_after_login()
    {
        $user = $this->createUser();

        $this->withHeader('referer', 'https://evil.example.com/dashboard')->get('/login');
        $this->flushHeaders();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertStringStartsWith('http://localhost/', $response->headers->get('Location'));
    }

    public function test_user_can_logout()
    {
        $this->actingAs($this->createUser())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_user_can_logout_using_get_request()
    {
        $this->actingAs($this->createUser())
            ->get('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_guest_cannot_logout()
    {
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_user_is_redirected_home_after_logout_and_login()
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/home');
    }
}
