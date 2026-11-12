<?php

namespace Tests\Feature;

use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolation between users (IDOR) and session.
 *
 * These tests are the automated proof of what docs/security.md shows with
 * curl: if someone removes the policy or the 'auth' middleware, CI breaks.
 */
class TaskAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/tasks')->assertRedirect('/login');
    }

    public function test_user_sees_only_their_own_tasks()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $anasTask = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Ana task',
        ]);

        factory(Task::class)->create([
            'user_id' => $bruno->id,
            'title' => 'Bruno task',
        ]);

        $this->actingAs($ana)
            ->get('/tasks')
            ->assertStatus(200)
            ->assertSee($anasTask->title)
            ->assertDontSee('Bruno task');
    }

    public function test_user_cannot_open_someone_elses_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($bruno)
            ->get('/tasks/' . $task->id)
            ->assertStatus(403);
    }

    public function test_user_cannot_edit_someone_elses_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($bruno)
            ->get('/tasks/' . $task->id . '/edit')
            ->assertStatus(403);
    }

    public function test_user_cannot_update_someone_elses_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Original',
        ]);

        $this->actingAs($bruno)
            ->put('/tasks/' . $task->id, ['title' => 'Hijacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Original',
        ]);
    }

    public function test_user_cannot_delete_someone_elses_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($bruno)
            ->delete('/tasks/' . $task->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
