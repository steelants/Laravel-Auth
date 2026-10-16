# Usage

SteelAnts Laravel-Auth provides authentication using a published `AuthController` and a route mixin.

The package handles:

- Login and logout
- User registration
- Password reset
- Email verification
- TOTP two-factor authentication


## Registering Routes

Authentication routes are registered in `routes/web.php`:

```php
Route::auth();
```

The routes are only available when `App\Http\Controllers\AuthController` exists.

For the full route list see:

[Routes documentation](routes.md)


## The AuthController

The installer publishes a minimal controller:

```php
namespace App\Http\Controllers;

use SteelAnts\LaravelAuth\Traits\Authentication;

class AuthController extends Controller
{
    use Authentication;
}
```

The `Authentication` trait provides all core authentication actions.

Optional features are added using plugin traits:

```php
use SteelAnts\LaravelAuth\Traits\Authentication;
use SteelAnts\LaravelAuth\Traits\HandlesEmailVerification;
use SteelAnts\LaravelAuth\Traits\HandlesTotpVerification;

class AuthController extends Controller
{
    use Authentication;
    use HandlesEmailVerification; // email verification flows
    use HandlesTotpVerification;  // TOTP MFA flows
}
```


## Login

The login form uses the following fields:

| Field | Description |
|---|---|
| `email` | User e-mail address |
| `password` | User password |
| `remember` | Optional remember me checkbox |

Validation rules:

```php
'email' => 'required|email',
'password' => 'required',
```

After a successful login the user is redirected back to the page they came from (only pages of the application with an existing route) or to the default redirect path.

Failed login attempts are rate limited per e-mail and IP address: after 5 failed attempts further attempts are rejected for one minute with the `auth.throttle` message. A successful login resets the counter.


## Registration

The registration form uses the following fields:

| Field | Description |
|---|---|
| `name` | User name |
| `email` | User e-mail address |
| `password` | User password |
| `password_confirmation` | Password confirmation |

Validation rules:

```php
'name' => 'required|max:255',
'email' => 'required|email|unique:users',
'password' => 'required|confirmed|min:8',
```


## Password Reset

The password reset flow consists of:

1. Requesting a reset link by e-mail.
2. Opening the reset form using the token from the e-mail.
3. Submitting the new password.

Token routes are throttled to 6 requests per minute.


## Views

The controller renders the published views:

- `auth.login`
- `auth.registration`
- `auth.reset`
- `auth.verify`
- `auth.totp`

You can modify the published views freely.


## Redirect After Login

By default users are redirected to the `home` route.

You can customize the redirect path:

```php
public function redirectTo(): string
{
    return route('dashboard');
}
```

For more customization options see:

[Customization documentation](customization.md)


## Next Steps

Continue with:

- [Configuration](configuration.md)
- [Routes](routes.md)
- [Email Verification](email-verification.md)
- [Two-Factor Authentication](totp.md)
- [Customization](customization.md)
