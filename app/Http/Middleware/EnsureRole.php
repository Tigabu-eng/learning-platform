<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        // A super admin passes every check an admin passes.
        if (in_array('admin', $roles, true)) $roles[] = 'super_admin';
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            abort(403, 'You do not have access to this area.');
        }
        return $next($request);
    }
}
