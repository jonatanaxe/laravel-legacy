<?php

namespace Tests\Http;

/**
 * Optimistic lock (409) and trash (soft delete) with real requests.
 *
 * The 409 is the two-tabs path: the form carries `version`, the second
 * tab sends the old version and the server refuses instead of overwriting.
 *
 * The task created here is disposable and really leaves the database (force),
 * so the suite can run as many times as needed without filling it up.
 */
class LockTest extends HttpTestCase
{
    public function test_old_version_does_not_overwrite()
    {
        $ana = $this->ana();
        $title = 'verification-' . uniqid();

        $create = $ana->submit('POST', '/tasks', [
            '_token' => $ana->token('/tasks/create'),
            'title' => $title,
            'priority' => 'low',
        ]);
        $this->assertSame(302, $create->getStatusCode(), 'creates a disposable task');

        $task = $this->idByTitle($title);
        $this->assertGreaterThan(0, $task, 'the created task should show up on the API');

        $edit = $ana->get('/tasks/' . $task . '/edit');
        $token = $ana->fieldOf($edit, '_token');
        $version = (int) $ana->fieldOf($edit, 'version');

        $this->assertNotNull($ana->fieldOf($edit, 'version'), 'the edit form must carry the version');

        // Tab B: read the old version before tab A saved.
        $this->assertSame(409, $ana->submit('PUT', '/tasks/' . $task, [
            '_token' => $token,
            'title' => $title . '-tab-b',
            'version' => '9999',
        ])->getStatusCode(), 'a stale version must be 409');

        $this->assertSame(302, $ana->submit('PUT', '/tasks/' . $task, [
            '_token' => $token,
            'title' => $title . '-v2',
            'version' => (string) $version,
        ])->getStatusCode(), 'the right version updates');

        $this->assertSame(
            $version + 1,
            (int) $ana->field('/tasks/' . $task . '/edit', 'version'),
            'each write bumps the version'
        );

        $this->trashLifecycle($ana, $task, $token, $title . '-v2');
    }

    /**
     * Trash: deleting doesn't remove it from the database, it removes it from the
     * app. Restore brings it back. Force delete is the only path that really removes it.
     */
    private function trashLifecycle(Browser $ana, $task, $token, $title)
    {
        $this->assertSame(302, $ana->submit('DELETE', '/tasks/' . $task, ['_token' => $token])->getStatusCode(), 'apaga');
        $this->assertSame(404, $ana->status('GET', '/tasks/' . $task), 'a deleted task does not open');

        $this->assertSame(200, $ana->status('GET', '/tasks/trash'), 'trash opens');
        $this->assertStringContainsString($title, $ana->body($ana->get('/tasks/trash')), 'trash lists the deleted task');

        $this->assertSame(302, $ana->submit('PATCH', '/tasks/' . $task . '/restore', ['_token' => $token])->getStatusCode(), 'restaura');
        $this->assertSame(200, $ana->status('GET', '/tasks/' . $task), 'a restored task opens');

        $this->assertSame(302, $ana->submit('DELETE', '/tasks/' . $task, ['_token' => $token])->getStatusCode(), 'deletes again');
        $this->assertSame(302, $ana->submit('DELETE', '/tasks/' . $task . '/force', ['_token' => $token])->getStatusCode(), 'deletes for good');

        $this->assertSame(404, $ana->status('GET', '/tasks/' . $task), 'a permanently deleted task does not open');
        $this->assertSame(404, $this->api('GET', '/api/tasks/' . $task, $this->anaToken())->getStatusCode(), 'a permanently deleted task leaves the API');
    }

    private function idByTitle($title)
    {
        $payload = $this->apiJson('/api/tasks?q=' . rawurlencode($title), $this->anaToken());

        return isset($payload['data'][0]) ? (int) $payload['data'][0]['id'] : 0;
    }
}
