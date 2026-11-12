<?php

namespace Tests\Http;

/**
 * Headers and the session cookie. They only exist in the HTTP response: no
 * kernel test reads the header the browser receives.
 */
class HeadersTest extends HttpTestCase
{
    public function test_security_headers_present()
    {
        $response = $this->guest()->get('/');

        foreach (['x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy', 'content-security-policy'] as $header) {
            $this->assertNotSame('', $response->getHeaderLine($header), "header $header ausente");
        }
    }

    public function test_csp_does_not_allow_inline_script()
    {
        $csp = $this->guest()->get('/')->getHeaderLine('content-security-policy');

        $this->assertStringContainsString("script-src 'self'", $csp, 'CSP must restrict scripts to the origin');
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp, 'unsafe-inline defeats the CSP');
    }

    /**
     * Known tech debt, pinned down: Alpine 2 needs 'unsafe-eval'. This test
     * makes sure it stays the ONLY exception in script-src. It is the test to
     * flip in the Alpine 3 upgrade (CSP build, no eval).
     */
    public function test_unsafe_eval_is_the_only_script_exception()
    {
        $csp = $this->guest()->get('/')->getHeaderLine('content-security-policy');

        preg_match("/script-src ([^;]+)/", $csp, $match);

        $this->assertSame("'self' 'unsafe-eval'", trim($match[1] ?? ''), 'script-src must be exactly self + unsafe-eval (Alpine 2)');
    }

    public function test_hsts_only_over_https()
    {
        $hsts = $this->guest()->get('/')->getHeaderLine('strict-transport-security');

        if ($this->isHttps()) {
            $this->assertStringContainsString('max-age=31536000', $hsts, 'HSTS for 1 year');
        } else {
            $this->assertSame('', $hsts, 'over http the browser ignores HSTS - so it is not announced');
        }
    }

    public function test_session_cookie_has_flags()
    {
        $response = $this->guest()->get('/login');

        $cookie = '';
        foreach ($response->getHeader('set-cookie') as $line) {
            if (strpos($line, '_session=') !== false) {
                $cookie = strtolower($line);
            }
        }

        $this->assertNotSame('', $cookie, 'login should start a session (Set-Cookie)');

        $flags = ['httponly', 'samesite=lax'];

        // `secure` only over https: in CI the server is http and the cookie wouldn't come back.
        if ($this->isHttps()) {
            array_unshift($flags, 'secure');
        }

        foreach ($flags as $flag) {
            $this->assertStringContainsString($flag, $cookie, "session cookie without $flag");
        }
    }
}
