<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateTpdApi
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('tpd.api_token');
        if ($expected === '') {
            return response()->json(['message' => 'TPD API token is not configured.'], 503);
        }

        $provided = $request->bearerToken() ?: (string) $request->header('X-TPD-Token', '');
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
