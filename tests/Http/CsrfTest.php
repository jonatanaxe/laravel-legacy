<?php

namespace Tests\Http;

/**
 * CSRF and throttling - the two protections a Feature PHPUnit test can't
 * prove: Laravel disables VerifyCsrfToken under `runningUnitTests()`, and the
 * rate limiter needs real requests within the same time window.
 */
class CsrfTest extends HttpTestCase
{
    public function test_post_without_token_answers_419()
    {
        $this->assertSame(419, $this->guest()->status('POST', '/tasks', [
            'form_params' => ['title' => 'hack'],
        ]), 'a form without _token must be stopped before the controller');
    }

    public function test_token_from_another_session_does_not_work()
    {
        // A valid token, but from another cookie jar: the session doesn't match.
        $token = $this->ana()->token('/tasks');

        $this->assertSame(419, $this->guest()->status('POST', '/tasks', [
            'form_params' => ['_token' => $token, 'title' => 'hack'],
        ]), 'a token without its matching session is worthless');
    }

    public function test_login_throttle_blocks_the_sixth_attempt()
    {
        // A unique email per run: repeating the suite doesn't inherit the counter.
        $email = 'nobody.' . uniqid() . '@example.com';
        $browser = $this->browser();

        $status = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $status = $browser->login($email, 'wrong-password');
        }

        $this->assertSame(302, $status, 'the first 5 attempts return a validation error');

        // In 5.8 the throttle's 429 only shows up for clients asking for JSON.
        $this->assertSame(429, $browser->status('POST', '/login', [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                '_token' => $browser->token('/login'),
                'email' => $email,
                'password' => 'wrong-password',
            ],
        ]), 'the sixth attempt must be blocked');
    }
}
