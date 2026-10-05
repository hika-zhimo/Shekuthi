<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reject stale sessions or tokens after an account is disabled/deleted. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            abort(403, 'This account is no longer active.');
        }

        return $next($request);
    }
}
