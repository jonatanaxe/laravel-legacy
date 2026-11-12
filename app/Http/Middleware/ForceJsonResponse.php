<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Force JSON on the "api" group.
 *
 * Without this, the same endpoint answers 302 (redirect to /login), or 302
 * with a validation error, when the client forgets "Accept: application/json"
 * - and an API consumer treats a 302 as network success, not as a failure.
 *
 * The server decides, not the client's header: /api/* is JSON.
 */
class ForceJsonResponse
{
    public function handle($request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
