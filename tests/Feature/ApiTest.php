<?php

namespace Tests\Feature;

use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JSON API: the same domain, another transport.
 *
 * What is proven here is what breaks most often in this kind of endpoint:
 *  - no token gives 401 (not a 302 to /login);
 *  - a token only sees its own data (IDOR on the API);
 *  - someone else's task gives 403, not a silent 404 or a 200;
 *  - the token never comes back in the response body.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders($token)
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_no_token_answers_401_even_without_accept_json()
    {
        // An API client that forgets the Accept header must not get a 302.
        $this->get('/api/tasks')->assertStatus(401);
    }

    public function test_invalid_token_answers_401()
    {
        $this->withHeaders($this->apiHeaders('token-that-does-not-exist'))
            ->getJson('/api/tasks')
            ->assertStatus(401);
    }

    public function test_lists_only_the_token_owners_tasks()
    {
        $ana = factory(User::class)->create(['api_token' => 'token-ana']);
        $bruno = factory(User::class)->create(['api_token' => 'token-bruno']);

        factory(Task::class)->create(['user_id' => $ana->id, 'title' => 'Ana\'s']);
        factory(Task::class)->create(['user_id' => $bruno->id, 'title' => 'Bruno\'s']);

        $response = $this->withHeaders($this->apiHeaders('token-ana'))
            ->getJson('/api/tasks')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Ana\'s'])
            ->assertJsonMissing(['title' => 'Bruno\'s']);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_json_shape_is_stable()
    {
        $ana = factory(User::class)->create(['api_token' => 'token-ana']);

        factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Task with a due date',
            'priority' => 'high',
            'done' => false, // explicit: the factory randomizes it and the test would be flaky
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $task = $this->withHeaders($this->apiHeaders('token-ana'))
            ->getJson('/api/tasks')
            ->assertStatus(200)
            ->json('data.0');

        foreach (['id', 'title', 'description', 'priority', 'done', 'overdue', 'due_date', 'created_at', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $task);
        }

        $this->assertSame('high', $task['priority']);
        $this->assertTrue($task['overdue']);
        $this->assertFalse($task['done']);
    }

    public function test_someone_elses_task_gives_403()
    {
        $ana = factory(User::class)->create(['api_token' => 'token-ana']);
        $bruno = factory(User::class)->create(['api_token' => 'token-bruno']);
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->withHeaders($this->apiHeaders('token-bruno'))
            ->getJson('/api/tasks/' . $task->id)
            ->assertStatus(403);
    }

    public function test_owner_opens_their_own_task()
    {
        $ana = factory(User::class)->create(['api_token' => 'token-ana']);
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $response = $this->withHeaders($this->apiHeaders('token-ana'))
            ->getJson('/api/tasks/' . $task->id)
            ->assertStatus(200);

        $this->assertEquals($task->id, $response->json('data.id'));
    }

    public function test_invalid_filter_gives_422_not_500()
    {
        $ana = factory(User::class)->create(['api_token' => 'token-ana']);

        $this->withHeaders($this->apiHeaders('token-ana'))
            ->getJson('/api/tasks?status=qualquer')
            ->assertStatus(422);
    }

    public function test_token_does_not_leak_in_the_body()
    {
        factory(User::class)->create(['api_token' => 'token-secreto-123']);

        $this->withHeaders($this->apiHeaders('token-secreto-123'))
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertDontSee('token-secreto-123');
    }
}
