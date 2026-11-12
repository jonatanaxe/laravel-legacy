<?php

namespace Tests\Http;

/**
 * JSON API against the server: a real token in the header, not actingAs().
 *
 * Proves what usually breaks in integration: 401 instead of 302, 403 instead
 * of a filtered list, 422 on an invalid filter and the data/meta envelope.
 */
class ApiTest extends HttpTestCase
{
    public function test_no_token_answers_401()
    {
        $this->assertSame(401, $this->api('GET', '/api/tasks')->getStatusCode(), 'no token, no entry');
        $this->assertSame(401, $this->api('GET', '/api/tasks', 'token-that-does-not-exist')->getStatusCode(), 'invalid token, no entry');
    }

    public function test_valid_token_lists_the_tasks()
    {
        $this->assertSame(200, $this->api('GET', '/api/tasks', $this->anaToken())->getStatusCode());
    }

    public function test_token_cannot_reach_another_owners_task()
    {
        $this->assertSame(403, $this->api('GET', '/api/tasks/' . $this->personalTaskId(), $this->tokenFor('bruno@example.com'))
            ->getStatusCode(), 'someone else\'s personal task must be 403 on the API');
    }

    public function test_invalid_filter_answers_422()
    {
        $this->assertSame(422, $this->api('GET', '/api/tasks?status=invalid', $this->anaToken())->getStatusCode());
    }

    public function test_data_and_meta_envelope()
    {
        $payload = $this->apiJson('/api/tasks', $this->anaToken());

        $this->assertArrayHasKey('data', $payload, 'body paginates under data');
        $this->assertArrayHasKey('meta', $payload, 'pagination under meta');
        $this->assertArrayNotHasKey('api_token', $payload['data'][0], 'the token never comes back in the body');
    }
}
