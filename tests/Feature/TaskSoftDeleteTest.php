<?php

namespace Tests\Feature;

use App\Task;
use App\TaskEvent;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Soft delete: gone from the list, not from the database.
 *
 * A physical delete destroys the evidence of a record. With SoftDeletes the
 * row stays (deleted_at filled in), the audit trail keeps pointing to it and
 * the owner can restore it.
 */
class TaskSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_moves_to_trash_instead_of_destroying()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)
            ->delete('/tasks/' . $task->id)
            ->assertRedirect('/tasks');

        // The row exists, with deleted_at filled in.
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_deleted_task_leaves_the_list_and_show()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Will disappear',
        ]);

        $task->delete();

        $this->actingAs($ana)
            ->get('/tasks')
            ->assertDontSee('Will disappear');

        // Implicit binding applies the SoftDeletes scope: 404.
        $this->actingAs($ana)
            ->get('/tasks/' . $task->id)
            ->assertStatus(404);
    }

    public function test_trash_shows_only_the_users_own_tasks()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $daAna = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Deleted by Ana',
        ]);
        $doBruno = factory(Task::class)->create([
            'user_id' => $bruno->id,
            'title' => 'Deleted by Bruno',
        ]);

        $daAna->delete();
        $doBruno->delete();

        $this->actingAs($ana)
            ->get('/tasks/trash')
            ->assertStatus(200)
            ->assertSee('Deleted by Ana')
            ->assertDontSee('Deleted by Bruno');
    }

    public function test_restores_own_task()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Volta',
        ]);

        $task->delete();

        $this->actingAs($ana)
            ->patch('/tasks/' . $task->id . '/restore')
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_restore_someone_elses_deleted_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $task->delete();

        $this->actingAs($bruno)
            ->patch('/tasks/' . $task->id . '/restore')
            ->assertStatus(403);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_cannot_force_delete_someone_elses_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $task->delete();

        $this->actingAs($bruno)
            ->delete('/tasks/' . $task->id . '/force')
            ->assertStatus(403);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_force_delete_removes_the_row_but_keeps_the_trail()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $task->delete();

        $events = TaskEvent::where('task_id', $task->id)->count();
        $this->assertGreaterThan(0, $events, 'the trail should exist before the force delete');

        $this->actingAs($ana)
            ->delete('/tasks/' . $task->id . '/force')
            ->assertRedirect('/tasks/trash');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);

        // No FK on task_events on purpose: the history outlives its owner.
        // The force delete adds its own event, it doesn't repeat "deleted".
        $this->assertSame($events + 1, TaskEvent::where('task_id', $task->id)->count());

        $last = TaskEvent::where('task_id', $task->id)->latest('id')->first();
        $this->assertSame('force_deleted', $last->action);
    }

    public function test_guest_cannot_access_the_trash()
    {
        $this->get('/tasks/trash')->assertRedirect('/login');
    }
}
