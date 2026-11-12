<?php

namespace Tests\Feature;

use App\Notifications\TaskDueSoon;
use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Reminder queue: who gets it, when, and only once.
 *
 * Notification::fake() intercepts delivery: the test checks the command's
 * decision, not the transport. The database channel and the notification's
 * ShouldQueue are covered by the contract (via() and the interface), not by real email.
 */
class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private function taskFor(User $user, array $overrides = [])
    {
        // Explicit done=false: the factory randomizes it, and a reminder test
        // can't depend on luck.
        return factory(Task::class)->create(array_merge([
            'user_id' => $user->id,
            'done' => false,
        ], $overrides));
    }

    public function test_command_notifies_a_task_due_by_tomorrow()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $task = $this->taskFor($ana, ['due_date' => now()->addDay()->toDateString()]);

        $this->artisan('tasks:due-soon')->assertExitCode(0);

        Notification::assertSentTo($ana, TaskDueSoon::class);
        $this->assertNotNull($task->fresh()->reminded_at);
    }

    public function test_overdue_task_is_reminded_too()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $this->taskFor($ana, ['due_date' => now()->subDays(3)->toDateString()]);

        $this->artisan('tasks:due-soon')->assertExitCode(0);

        Notification::assertSentToTimes($ana, TaskDueSoon::class, 1);
    }

    public function test_done_task_gets_no_reminder()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $this->taskFor($ana, [
            'done' => true,
            'due_date' => now()->toDateString(),
        ]);

        $this->artisan('tasks:due-soon')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_task_due_far_away_gets_no_reminder()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $this->taskFor($ana, ['due_date' => now()->addDays(5)->toDateString()]);

        $this->artisan('tasks:due-soon')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_task_without_due_date_gets_no_reminder()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $this->taskFor($ana, ['due_date' => null]);

        $this->artisan('tasks:due-soon')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_running_twice_does_not_duplicate_the_reminder()
    {
        // No Notification::fake(): the database channel has to really write,
        // otherwise the test proves no idempotency at all.
        $ana = factory(User::class)->create();
        $task = $this->taskFor($ana, ['due_date' => now()->toDateString()]);

        $this->artisan('tasks:due-soon')->assertExitCode(0);
        $this->artisan('tasks:due-soon')->assertExitCode(0);

        // Idempotency through the atomic claim on reminded_at, not in-memory
        // state: two concurrent workers get the same result.
        $this->assertSame(1, $ana->notifications()->count());
        $this->assertNotNull($task->fresh()->reminded_at);
    }

    public function test_dry_run_neither_sends_nor_marks()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $task = $this->taskFor($ana, ['due_date' => now()->toDateString()]);

        $this->artisan('tasks:due-soon', ['--dry-run' => true])
            ->expectsOutput('1 task(s) would be reminded.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull($task->fresh()->reminded_at);
    }

    public function test_reminder_does_not_pollute_the_audit_trail()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $task = $this->taskFor($ana, ['due_date' => now()->toDateString()]);

        $before = $task->events()->count();

        $this->artisan('tasks:due-soon')->assertExitCode(0);

        // The claim update goes through the query builder: the observer doesn't fire.
        $this->assertSame($before, $task->events()->count());
    }

    public function test_owner_on_demand_reminder()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $task = $this->taskFor($ana);

        $this->actingAs($ana)
            ->post(route('tasks.remind', $task))
            ->assertRedirect();

        Notification::assertSentTo($ana, TaskDueSoon::class);
    }

    public function test_third_party_cannot_trigger_a_reminder_on_someone_elses_task()
    {
        Notification::fake();

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = $this->taskFor($ana);

        $this->actingAs($bruno)
            ->post(route('tasks.remind', $task))
            ->assertStatus(403);

        Notification::assertNothingSent();
    }

    public function test_reminder_inbox_marks_as_read()
    {
        // No fake: the notification has to exist in the notifications table,
        // because that is what the inbox lists and marks as read.
        $ana = factory(User::class)->create();
        $task = $this->taskFor($ana, ['due_date' => now()->toDateString()]);

        $this->artisan('tasks:due-soon');

        $notification = $ana->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        $this->actingAs($ana)->get('/notifications')->assertStatus(200)
            ->assertSee($task->title);

        $this->actingAs($ana)
            ->patch('/notifications/' . $notification->id . '/read')
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_read_another_users_reminder()
    {
        // No fake: the 404 comes from the query scoped to the logged-in user
        // (auth()->user()->notifications()), not from an in-memory array.
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = $this->taskFor($ana, ['due_date' => now()->toDateString()]);

        $this->artisan('tasks:due-soon');

        $notification = $ana->notifications()->firstOrFail();

        // 404, not 403: it doesn't confirm the id exists in someone else's account.
        $this->actingAs($bruno)
            ->patch('/notifications/' . $notification->id . '/read')
            ->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);
    }
}
