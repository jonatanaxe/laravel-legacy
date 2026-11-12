<?php

namespace Tests\Feature;

use App\Task;
use App\TaskEvent;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Optimistic lock: the last one to save doesn't silently win.
 *
 * Real scenario: the same task open in two tabs. Tab A saves first; tab B
 * still has the old version in its form. Without this, tab B's PUT would
 * overwrite A's change without anyone noticing.
 */
class TaskConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_version_gets_409_and_does_not_overwrite()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Original',
            'version' => 0,
        ]);

        // Tab A saves: version 0 -> 1.
        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, [
                'title' => 'Saved by tab A',
                'version' => 0,
            ])
            ->assertRedirect('/tasks');

        // Tab B is still on version 0.
        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, [
                'title' => 'Saved by tab B',
                'version' => 0,
            ])
            ->assertStatus(409);

        $task = $task->fresh();

        $this->assertSame('Saved by tab A', $task->title);
        $this->assertSame(1, $task->version);
    }

    public function test_right_version_increments_and_saves()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Before',
            'version' => 7,
        ]);

        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, [
                'title' => 'After',
                'version' => 7,
            ])
            ->assertRedirect('/tasks');

        $task = $task->fresh();

        $this->assertSame('After', $task->title);
        $this->assertSame(8, $task->version);
    }

    public function test_version_does_not_pollute_the_audit_trail()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'version' => 0,
        ]);

        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, [
                'title' => 'New title',
                'version' => 0,
            ]);

        $event = TaskEvent::where('task_id', $task->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);

        $changed = json_decode($event->meta, true)['changed'];

        $this->assertArrayHasKey('title', $changed);
        $this->assertArrayNotHasKey('version', $changed);
    }

    public function test_version_is_not_mass_assignable()
    {
        $ana = factory(User::class)->create();

        // POST with version=99: the field is neither in $fillable nor in
        // validated(), so the task is born at version 0.
        $this->actingAs($ana)
            ->post('/tasks', [
                'title' => 'Task with a forged version',
                'version' => 99,
            ])
            ->assertRedirect('/tasks');

        $task = Task::where('title', 'Task with a forged version')->firstOrFail();

        $this->assertSame(0, $task->version);
    }

    public function test_client_without_version_is_treated_as_version_zero()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => $ana->id,
            'version' => 0,
        ]);

        // Backward compatible: on the first save the version is still 0.
        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, ['title' => 'Legacy'])
            ->assertRedirect('/tasks');

        // After that, a client that never sends version is locked out.
        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, ['title' => 'Legacy 2'])
            ->assertStatus(409);
    }
}
