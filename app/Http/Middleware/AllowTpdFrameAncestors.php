<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowTpdFrameAncestors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $ancestors = trim((string) config('tpd.frame_ancestors'));
        if ($ancestors === '') {
            return $response;
        }

        $response->headers->remove('X-Frame-Options');

        $existingCsp = $response->headers->get('Content-Security-Policy');
        if ($existingCsp) {
            if (! str_contains($existingCsp, 'frame-ancestors')) {
                $response->headers->set('Content-Security-Policy', rtrim($existingCsp, '; ')."; frame-ancestors {$ancestors}");
            }
        } else {
            $response->headers->set('Content-Security-Policy', "frame-ancestors {$ancestors}");
        }

        return $response;
    }
}
