<?php

namespace SteelAnts\LaravelAuth\Routing;

use SteelAnts\LaravelAuth\Middleware\EnsureEmailIsVerified;
use SteelAnts\LaravelAuth\Middleware\EnsureTotpVerified;

class AuthRoutesMixin
{
    public function auth()
    {
        return function ($options = []) {
            $namespace = 'App\Http\Controllers';
            $this->group(['namespace' => $namespace], function () use ($options) {
                if ($options['login'] ?? true) {
                    $this->get('/login', 'AuthController@login')->middleware('guest')->name('login');
                    $this->post('/login', 'AuthController@loginPost')->middleware('guest')->name('login.submit');
                }

                if ($options['logout'] ?? true) {
                    $this->post('/logout', 'AuthController@logout')->middleware('auth')->withoutMiddleware([EnsureEmailIsVerified::class, EnsureTotpVerified::class])->name('logout');
                    $this->get('/logout', 'AuthController@logout')->middleware('auth')->withoutMiddleware([EnsureEmailIsVerified::class, EnsureTotpVerified::class]);
                }

                if ($options['register'] ?? true) {
                    $this->get('/register', 'AuthController@register')->middleware('guest')->name('register');
                    $this->post('/register', 'AuthController@registerPost')->middleware('guest')->name('register.submit');
                }

                if ($options['reset'] ?? true) {
                    $this->get('/password/reset', 'AuthController@reset')->middleware('guest')->name('password');
                    $this->post('/password/email', 'AuthController@resetPost')->middleware('guest')->name('password.email');
                    $this->get('/password/reset/{token}', 'AuthController@resetToken')->middleware(['guest', 'throttle:6,1'])->name('password.reset');
                    $this->post('/password/reset/', 'AuthController@resetPasswordSubmit')->middleware(['guest', 'throttle:6,1'])->name('password.update');
                }

                if ($options['verify'] ?? false) {
                    $this->pushMiddlewareToGroup('web', EnsureEmailIsVerified::class);
                    $this->get('/email/verify', 'AuthController@verificationNotice')->middleware('auth')->withoutMiddleware([EnsureEmailIsVerified::class])->name('verification.notice');
                    $this->get('/email/verify/{id}/{hash}', 'AuthController@verifyEmail')->middleware(['auth', 'signed', 'throttle:6,1'])->withoutMiddleware([EnsureEmailIsVerified::class])->name('verification.verify');
                    $this->post('/email/resend', 'AuthController@resendVerification')->middleware(['auth', 'throttle:6,1'])->withoutMiddleware([EnsureEmailIsVerified::class])->name('verification.resend');
                }

                if ($options['totp'] ?? false) {
                    $this->pushMiddlewareToGroup('web', EnsureTotpVerified::class);
                    $this->get('/two-factor', 'AuthController@totpPrompt')->middleware('auth')->withoutMiddleware([EnsureTotpVerified::class])->name('totp.prompt');
                    $this->post('/two-factor', 'AuthController@totpVerify')->middleware(['auth', 'throttle:10,1'])->withoutMiddleware([EnsureTotpVerified::class])->name('totp.verify');
                }
            });
        };
    }
}
