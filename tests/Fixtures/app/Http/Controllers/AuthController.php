<?php

namespace App\Http\Controllers;

use SteelAnts\LaravelAuth\Traits\Authentication;
use SteelAnts\LaravelAuth\Traits\HandlesEmailVerification;
use SteelAnts\LaravelAuth\Traits\HandlesTotpVerification;

/**
 * Mirrors the controller from docs/usage.md with all optional feature traits enabled.
 */
class AuthController extends Controller
{
    use Authentication;
    use HandlesEmailVerification;
    use HandlesTotpVerification;
}
