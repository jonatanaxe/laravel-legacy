<?php

namespace Tests\Feature;

use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real CRUD: creation, server-side validation and mass assignment.
 *
 * Note: CSRF doesn't show up here because Laravel disables VerifyCsrfToken
 * in the test environment (runningUnitTests()). The 419 proof is an
 * integration test (real HTTP) in tests/Http, via `php artisan lab:verify`.
 */
class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_task_owned_by_the_session_user()
    {
        $ana = factory(User::class)->create();

        $this->actingAs($ana)
            ->post('/tasks', [
                'title' => 'New task',
                'description' => 'created by the test',
                'done' => 1,
            ])
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $ana->id,
            'title' => 'New task',
            'done' => true,
        ]);
    }

    public function test_user_id_injected_into_the_post_is_dropped()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $this->actingAs($ana)
            ->post('/tasks', [
                'title' => 'Hijack attempt',
                'user_id' => $bruno->id,
            ])
            ->assertRedirect('/tasks');

        // The task is born Ana's: the field isn't in $fillable and validated()
        // doesn't even return it. The client doesn't choose the owner.
        $this->assertDatabaseHas('tasks', [
            'title' => 'Hijack attempt',
            'user_id' => $ana->id,
        ]);

        $this->assertDatabaseMissing('tasks', [
            'title' => 'Hijack attempt',
            'user_id' => $bruno->id,
        ]);
    }

    public function test_short_title_fails_server_validation()
    {
        $ana = factory(User::class)->create();

        $this->actingAs($ana)
            ->from('/tasks/create')
            ->post('/tasks', ['title' => 'ab'])
            ->assertRedirect('/tasks/create')
            ->assertSessionHasErrors('title');

        $this->assertDatabaseMissing('tasks', ['title' => 'ab']);
    }

    public function test_title_is_required()
    {
        $ana = factory(User::class)->create();

        $this->actingAs($ana)
            ->from('/tasks/create')
            ->post('/tasks', ['description' => 'no title'])
            ->assertRedirect('/tasks/create')
            ->assertSessionHasErrors('title');
    }

    public function test_updates_own_task()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Before',
        ]);

        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, [
                'title' => 'After',
                'done' => 1,
            ])
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'After',
            'done' => true,
        ]);
    }

    public function test_deletes_own_task()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)
            ->delete('/tasks/' . $task->id)
            ->assertRedirect('/tasks');

        // Soft delete: gone from the lists, not from the database.
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
