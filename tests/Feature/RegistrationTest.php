<?php

namespace SteelAnts\LaravelAuth\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use SteelAnts\LaravelAuth\Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_page_can_be_rendered()
    {
        $this->get('/register')
            ->assertOk()
            ->assertViewIs('auth.registration')
            ->assertSee(route('register.submit'), false);
    }

    public function test_user_can_register()
    {
        $this->from('/register')->post('/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect('/register')->assertSessionHas('success');

        $user = User::where('email', 'john@example.com')->firstOrFail();

        $this->assertSame('John Doe', $user->name);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertGuest();
    }

    public function test_registered_user_can_login()
    {
        $this->post('/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->post('/login', [
            'email'    => 'john@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/home');

        $this->assertAuthenticated();
    }

    public function test_registration_requires_all_fields()
    {
        $this->from('/register')
            ->post('/register', [])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_registration_requires_unique_email()
    {
        $existing = $this->createUser();

        $this->from('/register')->post('/register', [
            'name'                  => 'John Doe',
            'email'                 => $existing->email,
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_registration_requires_password_confirmation()
    {
        $this->from('/register')->post('/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'other-password',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_registration_requires_password_with_at_least_eight_characters()
    {
        $this->from('/register')->post('/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_authenticated_user_cannot_see_registration_page()
    {
        $this->actingAs($this->createUser())
            ->get('/register')
            ->assertRedirect();
    }
}
