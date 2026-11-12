<?php

namespace Tests\Feature;

use App\Flow\Chart;
use App\Flow\FlowMetrics;
use App\Project;
use App\Task;
use App\TaskEvent;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flow and project overview.
 *
 * What is proven here:
 *  - the chart series is math, not drawing: the cumulative never decreases
 *    and closes on the real total of tasks;
 *  - percentiles use rank, so a forgotten task doesn't move the p85;
 *  - the time window is clamped on the server (`?days=99999` doesn't scan the
 *    table, `?days=abc` doesn't divide by zero);
 *  - `status` is the source and `done` the mirror - they never diverge;
 *  - the project overview is no back door: personal tasks stay out of the
 *    project timeline, and outsiders get 403.
 */
class FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // A leaking fake clock breaks every following test.
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function projectFor(User $owner)
    {
        $project = new Project(['name' => 'Test migration']);
        $project->user_id = $owner->id;
        $project->save();
        $project->users()->attach($owner->id, ['role' => 'owner']);

        return $project;
    }

    private function taskIn(Project $project, User $owner, array $attributes)
    {
        $task = factory(Task::class)->create(array_merge([
            'user_id' => $owner->id,
        ], $attributes));

        $task->project_id = $project->id;
        $task->save();

        return $task;
    }

    /* ---------------------------------------------------------------------
     | Access
     * ------------------------------------------------------------------- */

    public function test_guest_cannot_open_the_flow()
    {
        $this->get('/flow')->assertRedirect('/login');
    }

    public function test_flow_draws_the_svg_on_the_server()
    {
        $user = factory(User::class)->create();
        factory(Task::class)->create(['user_id' => $user->id, 'status' => 'doing', 'done' => false]);

        $response = $this->actingAs($user)->get('/flow');

        $response->assertStatus(200);
        $response->assertSee('Workflow');
        // The SVG arrives ready in the HTML: no chart library, no JS.
        $response->assertSee('<svg', false);
        $response->assertSee('band-doing', false);
    }

    public function test_project_timeline_shows_only_project_tasks()
    {
        $ana = factory(User::class)->create();
        $project = $this->projectFor($ana);

        $this->taskIn($project, $ana, [
            'title' => 'Inside the project',
            'status' => 'doing',
            'done' => false,
        ]);

        factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Ana personal task',
            'status' => 'backlog',
            'done' => false,
        ]);

        $response = $this->actingAs($ana)->get(route('projects.show', $project));

        $response->assertStatus(200);
        $response->assertSee('Inside the project');
        $response->assertDontSee('Ana personal task');
        $response->assertSee('Timeline');
        $response->assertSee('<svg', false);
    }

    public function test_outsider_gets_403_on_the_project()
    {
        $ana = factory(User::class)->create();
        $outsider = factory(User::class)->create();
        $project = $this->projectFor($ana);

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertStatus(403);
    }

    public function test_project_without_tasks_opens_without_blowing_up()
    {
        $ana = factory(User::class)->create();
        $project = $this->projectFor($ana);

        $response = $this->actingAs($ana)->get(route('projects.show', $project));

        $response->assertStatus(200);
        $response->assertSee('No tasks in the project yet');
    }

    /* ---------------------------------------------------------------------
     | Time window
     * ------------------------------------------------------------------- */

    public function test_days_window_is_clamped_on_the_server()
    {
        $this->assertSame(FlowMetrics::DEFAULT_DAYS, FlowMetrics::clampDays('abc'));
        $this->assertSame(FlowMetrics::DEFAULT_DAYS, FlowMetrics::clampDays(-5));
        $this->assertSame(FlowMetrics::DEFAULT_DAYS, FlowMetrics::clampDays(0));
        $this->assertSame(FlowMetrics::MAX_DAYS, FlowMetrics::clampDays(99999));
        $this->assertSame(FlowMetrics::MIN_DAYS, FlowMetrics::clampDays(1));
        $this->assertSame(45, FlowMetrics::clampDays(45));
    }

    /* ---------------------------------------------------------------------
     | Chart series
     * ------------------------------------------------------------------- */

    public function test_cfd_accumulates_without_decreasing_and_closes_on_the_real_total()
    {
        $user = factory(User::class)->create();

        Carbon::setTestNow(Carbon::today()->subDays(12)->setTime(9, 0));
        $task = factory(Task::class)->create([
            'user_id' => $user->id,
            'status' => 'backlog',
            'done' => false,
        ]);

        Carbon::setTestNow(Carbon::today()->subDays(8)->setTime(9, 0));
        $task->status = 'doing';
        $task->save();

        factory(Task::class)->create([
            'user_id' => $user->id,
            'status' => 'done',
        ]);

        Carbon::setTestNow();

        $flow = (new FlowMetrics($user, 30))->build();

        $previous = 0;

        foreach ($flow['cfd']['labels'] as $index => $label) {
            $sum = 0;

            foreach ($flow['cfd']['series'] as $stage => $values) {
                $sum += $values[$index];
            }

            $this->assertGreaterThanOrEqual(
                $previous,
                $sum,
                "the cumulative total dropped on $label - a task vanished from the system"
            );

            $previous = $sum;
        }

        $this->assertSame(2, $previous, 'on the last day the chart must add up every task');
        $this->assertSame(0, $flow['cfd']['series']['backlog'][0], 'a task that did not exist yet is not in the backlog');
    }

    public function test_timeline_does_not_start_with_an_empty_week()
    {
        $user = factory(User::class)->create();

        Carbon::setTestNow(Carbon::today()->subDays(40)->setTime(9, 0));
        factory(Task::class)->create(['user_id' => $user->id]);

        Carbon::setTestNow();

        $history = (new FlowMetrics($user))->build()['history'];

        $this->assertGreaterThan(0, $history['weeks'][0]['total'], 'the first row cannot be padding');
        $this->assertSame($history['weeks'][0]['range'], $history['from']);
        $this->assertTrue(end($history['weeks'])['current'], 'the last row is the current week');
    }

    public function test_percentile_uses_rank_not_mean()
    {
        $user = factory(User::class)->create();
        $createdAt = Carbon::today()->subDays(20)->setTime(9, 0);

        // Lead times of 10, 12, 14 and 16 days. The mean is 13; p50 = 12 and
        // p85 = 16, because a percentile is a rank, not a mean.
        foreach ([10, 12, 14, 16] as $days) {
            Carbon::setTestNow($createdAt);
            $task = factory(Task::class)->create([
                'user_id' => $user->id,
                'status' => 'backlog',
                'done' => false,
            ]);

            Carbon::setTestNow($createdAt->copy()->addDays($days));
            $task->status = 'done';
            $task->save();
        }

        Carbon::setTestNow();

        $times = (new FlowMetrics($user))->build()['times'];

        $this->assertSame(4, $times['lead']['n']);
        $this->assertSame(12.0, $times['lead']['p50']);
        $this->assertSame(16.0, $times['lead']['p85']);
        $this->assertSame(13.0, $times['lead']['avg']);
    }

    public function test_stacked_bar_fills_the_width_and_ignores_empty_stages()
    {
        $chart = Chart::stack([
            ['key' => 'backlog', 'value' => 1],
            ['key' => 'doing', 'value' => 2],
            ['key' => 'review', 'value' => 0],
            ['key' => 'done', 'value' => 1],
        ], 400, 10);

        $this->assertSame(4, $chart['total']);
        $this->assertEquals(400, array_sum(array_column($chart['segments'], 'w')), 'sum of bands = width', 0.2);
        $this->assertSame(50, $chart['segments'][1]['pct']);
        $this->assertEquals(0, $chart['segments'][2]['w']);

        $empty = Chart::stack([['key' => 'done', 'value' => 0]], 200);

        $this->assertTrue($empty['empty']);
        $this->assertSame(0, $empty['total']);
    }

    /* ---------------------------------------------------------------------
     | status x done
     * ------------------------------------------------------------------- */

    public function test_status_and_done_never_diverge()
    {
        $task = factory(Task::class)->create(['status' => 'backlog', 'done' => false]);

        $this->assertFalse($task->done);

        $task->status = 'done';
        $this->assertTrue($task->done, 'status done must mirror into done');

        $task->done = false;
        $this->assertSame('backlog', $task->status, 'unchecking done goes back to backlog');

        $task->status = 'invented-stage';
        $this->assertSame('backlog', $task->status, 'a value outside the list does not get in, not even through the model');
    }

    /**
     * The count label is not the badge label: "17 done" must read right
     * after a number. The timeline adds up, so it needs the plural form for
     * every stage - and fails loudly if a new stage arrives without a label.
     */
    public function test_count_label_covers_every_stage()
    {
        $plural = Task::statusLabelsPlural();

        foreach (Task::STATUSES as $stage) {
            $this->assertArrayHasKey($stage, $plural, "stage $stage has no count label");
            $this->assertNotSame('', $plural[$stage]);
        }

        $this->assertSame('done', $plural['done']);
        $this->assertCount(count(Task::STATUSES), $plural);
    }

    public function test_server_rejects_a_stage_outside_the_list()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Invented stage',
            'priority' => 'normal',
            'status' => 'arquivada',
        ])->assertSessionHasErrors('status');

        $this->assertSame(0, Task::where('title', 'Invented stage')->count());
    }

    public function test_stage_change_records_an_event_with_from_and_to()
    {
        $task = factory(Task::class)->create(['status' => 'backlog', 'done' => false]);

        $task->status = 'review';
        $task->save();

        $event = TaskEvent::where('task_id', $task->id)
            ->where('action', 'status_changed')
            ->first();

        $this->assertNotNull($event, 'moving the stage must leave a trail');

        $meta = $event->metaArray();

        $this->assertSame('backlog', $meta['changed']['status']['from']);
        $this->assertSame('review', $meta['changed']['status']['to']);
        $this->assertSame('in review', $task->statusLabel());
    }
}
