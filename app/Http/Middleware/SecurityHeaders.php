<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Security headers on every response.
 *
 * Without them the browser is free to guess file types (nosniff), embed the
 * page in another site's iframe (clickjacking) and leak the URL in the
 * Referer. One-line headers that close whole classes of bugs.
 *
 * CSP: blocks scripts from other origins and inline scripts. That is why
 * this project has no onclick/onsubmit in HTML - the delete confirm() lives
 * in public/js/lab.js. 'unsafe-inline' appears only in style-src because the
 * CSS ships inline.
 *
 * TECH DEBT: 'unsafe-eval' in script-src. Alpine 2 evaluates HTML
 * expressions (x-data, @click) with new Function(), and a CSP without
 * unsafe-eval would block all of them. Inline scripts remain blocked. It goes
 * away in the Alpine 3 upgrade, which ships a CSP build (@alpinejs/csp)
 * without eval.
 *
 * HSTS only when the request arrived over HTTPS: sending
 * Strict-Transport-Security over http is ignored by the browser, and sending
 * it on a production http site would lock users out. The condition avoids
 * both.
 */
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (!method_exists($response, 'header')) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; "
            . "img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-eval'"
        );

        if ($request->isSecure()) {
            // 1 year; includeSubDomains covers a forgotten http subdomain.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

