<?php

namespace Tests\Http;

/**
 * Session and authorization against the real server.
 *
 * Each account uses its own cookie jar, so "ana" and "bruno" are two truly
 * separate sessions - something Feature PHPUnit doesn't reproduce
 * faithfully (there, auth state is simulated by the kernel).
 */
class SessionTest extends HttpTestCase
{
    public function test_public_pages_open()
    {
        $guest = $this->guest();

        $this->assertSame(200, $guest->status('GET', '/'), '/ must open for guests');
        $this->assertSame(200, $guest->status('GET', '/login'), '/login must open');
        $this->assertSame(200, $guest->status('GET', '/register'), '/register must open');
    }

    public function test_task_list_requires_login()
    {
        $this->assertSame(302, $this->guest()->status('GET', '/tasks'), 'guests must be redirected');
    }

    public function test_owner_opens_their_own_task()
    {
        $ana = $this->ana();
        $id = $this->personalTaskId();

        $this->assertSame(200, $ana->status('GET', '/tasks'), 'ana\'s list opens');
        $this->assertSame(200, $ana->status('GET', '/tasks/' . $id), 'the owner opens the task');
        $this->assertSame(200, $ana->status('GET', '/tasks/' . $id . '/edit'), 'the owner opens the form');
    }

    public function test_another_user_cannot_open_a_personal_task()
    {
        $id = $this->personalTaskId();

        $this->assertSame(403, $this->bruno()->status('GET', '/tasks/' . $id), 'IDOR on show must be 403');
        $this->assertSame(403, $this->bruno()->status('GET', '/tasks/' . $id . '/edit'), 'IDOR on edit must be 403');
        $this->assertSame(200, $this->bruno()->status('GET', '/tasks'), 'his own list still opens');
    }

    public function test_project_roles_decide_access()
    {
        $project = $this->projectId();
        $task = $this->projectTaskId();

        $this->assertSame(200, $this->ana()->status('GET', '/projects'), 'owner lists projects');
        $this->assertSame(200, $this->ana()->status('GET', '/projects/' . $project), 'owner opens the project');
        $this->assertSame(200, $this->bruno()->status('GET', '/projects/' . $project), 'member opens the project');
        $this->assertSame(200, $this->bruno()->status('GET', '/tasks/' . $task), 'member opens the project task');
        $this->assertSame(200, $this->carla()->status('GET', '/tasks/' . $task), 'viewer opens the project task');
    }

    public function test_role_blocks_writes()
    {
        $project = $this->projectId();
        $task = $this->projectTaskId();

        $bruno = $this->bruno();
        $this->assertSame(403, $bruno->submit('DELETE', '/projects/' . $project, [
            '_token' => $bruno->token('/projects'),
        ])->getStatusCode(), 'a member cannot delete the project');

        $carla = $this->carla();
        $this->assertSame(403, $carla->status('GET', '/tasks/' . $task . '/edit'), 'a viewer cannot edit');
        $this->assertSame(403, $carla->submit('DELETE', '/tasks/' . $task, [
            '_token' => $carla->token('/tasks'),
        ])->getStatusCode(), 'a viewer cannot delete');
    }
}
