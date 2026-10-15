<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Gates the read-only model API behind a bearer token — see ApiToken.
     * This is deliberately separate from the normal web session/2FA guard:
     * callers here are a script or an assistant, never a browser.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken();

        if (! $plaintext) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        $token = ApiToken::findByPlaintext($plaintext);

        if (! $token) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        $token->last_used_at = now();
        $token->save();

        return $next($request);
    }
}
