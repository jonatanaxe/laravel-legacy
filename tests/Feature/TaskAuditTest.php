<?php

namespace Tests\Feature;

use App\Task;
use App\TaskEvent;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit: the observer must record creation, edits and status transitions,
 * with author and diff. If someone removes observe() from the provider, these
 * tests break - and the trail doesn't vanish silently.
 */
class TaskAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_records_an_event_with_author()
    {
        $ana = factory(User::class)->create();

        $this->actingAs($ana)->post('/tasks', ['title' => 'With a trail']);

        $task = Task::where('title', 'With a trail')->firstOrFail();
        $event = TaskEvent::where('task_id', $task->id)->firstOrFail();

        $this->assertEquals('created', $event->action);
        $this->assertEquals($ana->id, $event->user_id);
        $this->assertEquals('With a trail', $event->metaArray()['title']);
    }

    public function test_edit_records_a_diff_of_changed_fields_only()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id, 'title' => 'Before']);

        $this->actingAs($ana)->put('/tasks/' . $task->id, [
            'title' => 'After',
            'priority' => 'high',
        ]);

        $event = TaskEvent::where('task_id', $task->id)->where('action', 'updated')->firstOrFail();
        $changed = $event->metaArray()['changed'];

        $this->assertArrayHasKey('title', $changed);
        $this->assertEquals('Before', $changed['title']['from']);
        $this->assertEquals('After', $changed['title']['to']);
        $this->assertArrayHasKey('priority', $changed);
    }

    public function test_completing_has_its_own_action_in_the_trail()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id, 'done' => false]);

        $this->actingAs($ana)->put('/tasks/' . $task->id, ['title' => $task->title, 'done' => 1]);

        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'action' => 'completed',
        ]);
    }

    public function test_history_shows_on_the_task_screen()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)->get('/tasks/' . $task->id)
            ->assertStatus(200)
            ->assertSee('History')
            ->assertSee('created the task')
            ->assertSee($ana->name);
    }

    public function test_event_survives_the_task_delete()
    {
        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)->delete('/tasks/' . $task->id);

        // Soft delete: the row stays in the database, flagged. So does the trail.
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'action' => 'deleted',
        ]);
    }
}
