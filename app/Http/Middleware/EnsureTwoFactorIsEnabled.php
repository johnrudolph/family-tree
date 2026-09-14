<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    /**
     * Two-factor auth (or a passkey) is mandatory for every member before they can see
     * any tree content — this app holds family data for ~100 people, not one account.
     * Attach this to content routes only; onboarding/security (where it's set up) stays reachable regardless.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_confirmed_at === null && $user->passkeys()->doesntExist()) {
            return redirect()->route('onboarding.security');
        }

        return $next($request);
    }
}
