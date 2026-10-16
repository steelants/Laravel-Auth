<?php

namespace SteelAnts\LaravelAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use SteelAnts\LaravelAuth\Traits\HandlesTotp;

class EnsureTotpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!Route::has('totp.prompt')) {
            throw new LogicException('TOTP feature is disabled. Enable it via Route::auth(["totp" => true]).');
        }

        if (!$user) {
            return $next($request);
        }

        if ($response = $this->responseIfTotpRequired($request)) {
            return $response;
        }

        return $next($request);
    }

	 protected function responseIfTotpRequired(Request $request): Response|RedirectResponse|null
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        if (method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
            return null;
        }

        $hasTotp = !empty($user->totp_secret);
        $forceTotp = (bool) ($user->totp_force ?? false);
        $totpVerified = (bool) $request->session()->get('totp_passed');

        if (($forceTotp && !$hasTotp) || ($hasTotp && !$totpVerified)) {
            return $request->expectsJson() ? abort(403, 'Two-factor authentication required.') : redirect()->guest(route('totp.prompt'));
        }

        return null;
    }
}
