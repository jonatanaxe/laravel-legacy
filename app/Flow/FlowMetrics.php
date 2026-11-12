<?php

namespace App\Flow;

use App\Project;
use App\Task;
use App\TaskEvent;
use App\User;
use Carbon\Carbon;

/**
 * Process metrics - not task metrics.
 *
 * The question this class answers is not "what do I have to do" but
 * "how does work move through the system". That is why it reads *events*,
 * not only the current state: today's state doesn't know how long a task
 * sat in review.
 *
 * Source of truth: `task_events` (the trail the TaskObserver writes on any
 * write, whether from web, API, import or artisan). There is no metrics
 * table and no cron recounting anything - if the trail is right, the number
 * is right.
 *
 * Scope: `Task::visibleTo($user)`, the same authorization rule as the list.
 * A chart that shows the process can't be a back door to someone else's
 * tasks.
 *
 * Declared limits (and shown on screen):
 *  - trashed tasks are left out of the whole series (soft delete removes them
 *    from the flow; we don't rewrite the past of what was deleted);
 *  - tasks without events default to 'backlog' on their creation date -
 *    legacy data doesn't get an invented history.
 */
class FlowMetrics
{
    const MIN_DAYS = 7;
    const MAX_DAYS = 90;
    const DEFAULT_DAYS = 30;
    const THROUGHPUT_WEEKS = 6;

    /**
     * Cap on weeks in the project timeline. A 3-year project doesn't fit on one
     * screen; past that, the first week becomes noise and the reader stops
     * reading. The cut is declared in the section title.
     */
    const HISTORY_WEEKS = 14;

    /** @var User */
    private $user;

    /**
     * Optional project. When given, everything (CFD, throughput, lead time,
     * load) applies only to that project's tasks - and the visibility scope is
     * still applied on top, never instead.
     *
     * @var Project|null
     */
    private $project;

    /** @var int */
    private $days;

    /** @var Carbon */
    private $today;

    /** @var \Illuminate\Support\Collection */
    private $tasks;

    /** @var array<int, array<int, array{ts: Carbon, status: string}>> */
    private $timeline = [];

    /** @var Carbon[] */
    private $completions = [];

    /**
     * Flattened trail for the timeline narrative. Keeping it here avoids a
     * third query just to write "who moved what".
     *
     * @var array<int, array{ts: Carbon, action: string, user_id: int, task_id: int, title: string, status: string}>
     */
    private $eventLog = [];

    public function __construct(User $user, $days = self::DEFAULT_DAYS, Project $project = null)
    {
        $this->user = $user;
        $this->days = self::clampDays($days);
        $this->project = $project;
        $this->today = Carbon::today();
    }

    /**
     * Series window in days.
     *
     * User input, so it is clamped on the server: `?days=99999` can't scan the
     * whole table, and `?days=abc` can't become 0 and divide by zero in the
     * position math.
     */
    public static function clampDays($days)
    {
        $days = (int) $days;

        if ($days <= 0) {
            $days = self::DEFAULT_DAYS;
        }

        return max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
    }

    public function days()
    {
        return $this->days;
    }

    /**
     * Everything the page needs, in one array - the view doesn't query the DB.
     */
    public function build()
    {
        $this->load();

        return [
            'days' => $this->days,
            'stages' => Task::statusLabels(),
            'cfd' => $this->cfd(),
            'throughput' => $this->throughput(),
            'aging' => $this->aging(),
            'times' => $this->times(),
            'summary' => $this->summary(),
            'snapshot' => $this->snapshot(),
            'history' => $this->history(),
            'workload' => $this->workload(),
        ];
    }

    /* ---------------------------------------------------------------------
     | Load
     * ------------------------------------------------------------------- */

    private function load()
    {
        // Two queries, not N+1: visible tasks and their events.
        $query = Task::visibleTo($this->user)
            ->select(['id', 'user_id', 'title', 'status', 'done', 'due_date', 'created_at', 'deleted_at']);

        // Project cut: the visibility above still applies, so the cut never
        // becomes a back door to someone else's tasks.
        if ($this->project !== null) {
            $query->where('tasks.project_id', $this->project->id);
        }

        $this->tasks = $query->orderBy('created_at')->get();

        $this->timeline = [];
        $this->completions = [];
        $this->eventLog = [];

        $ids = $this->tasks->pluck('id')->all();

        if (empty($ids)) {
            return;
        }

        foreach ($ids as $id) {
            $this->timeline[(int) $id] = [];
        }

        $events = TaskEvent::query()
            ->whereIn('task_id', $ids)
            // Only what changes the stage: "edited the title" doesn't move the flow.
            ->whereIn('action', ['created', 'status_changed', 'completed', 'reopened', 'restored'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['task_id', 'user_id', 'action', 'meta', 'created_at']);

        $current = $this->tasks->keyBy('id');

        foreach ($events as $event) {
            $id = (int) $event->task_id;
            $task = $current->get($id);
            $status = $this->statusAfter($event, $task ? $task->status : 'backlog');

            $this->timeline[$id][] = [
                'ts' => $event->created_at,
                'status' => $status,
            ];

            $this->eventLog[] = [
                'ts' => $event->created_at,
                'action' => $event->action,
                'user_id' => (int) $event->user_id,
                'task_id' => $id,
                'title' => $task ? $task->title : null,
                'status' => $status,
            ];

            if ($event->action === 'completed') {
                $this->completions[] = $event->created_at;
            }
        }
    }

    /**
     * Stage resulting from an event.
     *
     * The meta may come from different versions of the trail, so there is a
     * fallback: the new column wins, the old `action` decides when it's missing.
     */
    private function statusAfter(TaskEvent $event, $current)
    {
        if ($event->action === 'created') {
            $meta = $event->metaArray();
            $status = isset($meta['status']) ? $meta['status'] : null;

            return in_array($status, Task::STATUSES, true) ? $status : 'backlog';
        }

        if ($event->action === 'restored') {
            return 'backlog';
        }

        $meta = $event->metaArray();
        $to = isset($meta['changed']['status']) ? TaskEvent::diffTo($meta['changed']['status']) : null;

        if (in_array($to, Task::STATUSES, true)) {
            return $to;
        }

        if ($event->action === 'completed') {
            return 'done';
        }

        if ($event->action === 'reopened') {
            return 'backlog';
        }

        // Event without a readable status: don't invent a stage, keep the current one.
        return $current;
    }

    /* ---------------------------------------------------------------------
     | CFD - cumulative flow diagram
     * ------------------------------------------------------------------- */

    /**
     * Amount of work in each stage at the end of each day.
     *
     * Reading: the total height of the stack is the known work; the thickness of
     * each band is that stage's WIP. A band that grows = work coming in faster
     * than it leaves (the bottleneck shows without any text).
     */
    private function cfd()
    {
        $start = $this->today->copy()->subDays($this->days - 1)->startOfDay();

        $labels = [];
        $days = [];

        for ($i = 0; $i < $this->days; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('M j');
            $days[] = $day;
        }

        $series = [];
        foreach (Task::STATUSES as $stage) {
            $series[$stage] = array_fill(0, $this->days, 0);
        }

        foreach ($this->tasks as $task) {
            $created = $task->created_at ? $task->created_at->copy()->startOfDay() : $start->copy();
            $timeline = isset($this->timeline[$task->id]) ? $this->timeline[$task->id] : [];

            foreach ($days as $index => $day) {
                $endOfDay = $day->copy()->endOfDay();

                // Didn't exist yet: not counted (not even in backlog).
                if ($created->gt($endOfDay)) {
                    continue;
                }

                $status = $this->stageAt($timeline, $endOfDay);

                if (isset($series[$status])) {
                    $series[$status][$index]++;
                }
            }
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'total' => $this->tasks->count(),
            'start' => $start->toDateString(),
        ];
    }

    /**
     * A task's stage at a given moment.
     *
     * The trail is chronological, so the last point that happened up to that
     * moment is the stage at that time. Without events the task is in the
     * backlog since creation - the honest default for legacy data.
     */
    private function stageAt(array $timeline, Carbon $moment)
    {
        $status = 'backlog';

        foreach ($timeline as $point) {
            if ($point['ts']->lte($moment)) {
                $status = $point['status'];
            } else {
                break;
            }
        }

        return $status;
    }

    /**
     * Work timeline: one row per week.
     *
     * Each row is a snapshot of the state at the end of that week - not the list
     * of that week's tasks. Each row's bar is the CFD reading turned vertical:
     * the green grows as work leaves.
     *
     * @return array<int, array>
     */
    private function history()
    {
        if ($this->tasks->isEmpty()) {
            return ['weeks' => [], 'from' => null, 'to' => null, 'weeks_total' => 0];
        }

        $first = $this->tasks->first()->created_at;

        // Weeks since the first task, capped: the cut is never chosen by an API
        // client.
        $span = $first
            ? (int) ceil($first->copy()->startOfWeek()->diffInDays($this->today) / 7) + 1
            : 8;

        $count = max(4, min(self::HISTORY_WEEKS, $span));
        $start = $this->today->copy()->startOfWeek()->subWeeks($count - 1);

        $stages = Task::STATUSES;
        $weeks = [];

        for ($i = 0; $i < $count; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

            // The current week ends today: the week's bar can't anticipate a Friday
            // that hasn't happened yet.
            $isCurrent = $i === $count - 1;
            if ($isCurrent) {
                $weekEnd = $this->today->copy()->endOfDay();
            }

            $byStage = array_fill_keys($stages, 0);
            $total = 0;

            foreach ($this->tasks as $task) {
                $created = $task->created_at ? $task->created_at->copy()->startOfDay() : $weekStart->copy();

                if ($created->gt($weekEnd)) {
                    continue;
                }

                $status = $this->stageAt(
                    isset($this->timeline[$task->id]) ? $this->timeline[$task->id] : [],
                    $weekEnd
                );

                if (isset($byStage[$status])) {
                    $byStage[$status]++;
                }

                $total++;
            }

            $created = 0;
            $moved = 0;
            $highlight = [];

            foreach ($this->eventLog as $event) {
                if ($event['ts']->lt($weekStart) || $event['ts']->gt($weekEnd)) {
                    continue;
                }

                if ($event['action'] === 'created') {
                    $created++;

                    continue;
                }

                $moved++;

                // Two deliveries count more than twenty stage edits: the narrative
                // prioritizes what took work out of the system.
                if (count($highlight) < 3 || $event['action'] === 'completed') {
                    $highlight[] = $event;
                }
            }

            // Completions within the week (the completed event also moves; here the
            // number is only for the row label).
            $done = 0;
            foreach ($this->completions as $completedAt) {
                if ($completedAt->gte($weekStart) && $completedAt->lte($weekEnd)) {
                    $done++;
                }
            }

            // Most recent first within the row, which is how it is read.
            usort($highlight, function ($a, $b) {
                return $b['ts']->timestamp - $a['ts']->timestamp;
            });

            $states = [];
            foreach ($stages as $stage) {
                $states[] = ['key' => $stage, 'value' => $byStage[$stage]];
            }

            $weeks[] = [
                'label' => $weekStart->format('M j'),
                'range' => $weekStart->format('M j') . ' – ' . $weekEnd->format('M j'),
                'by_stage' => $byStage,
                'states' => $states,
                'total' => $total,
                'done' => $byStage['done'],
                'percent' => $total > 0 ? (int) round($byStage['done'] / $total * 100) : 0,
                'entered' => $created,
                'moved' => $moved,
                'completed' => $done,
                'current' => $isCurrent,
                'highlight' => array_slice($highlight, 0, 3),
            ];
        }

        // Weeks before the first task are padding, not history: trim the empty
        // ones at the edge. A row with nothing to say only pushes content
        // down.
        while (count($weeks) > 1 && $weeks[0]['total'] === 0) {
            array_shift($weeks);
        }

        return [
            'weeks' => $weeks,
            'from' => $weeks[0]['range'],
            'to' => $weeks[count($weeks) - 1]['range'],
            'weeks_total' => count($weeks),
        ];
    }

    /* ---------------------------------------------------------------------
     | Throughput
     * ------------------------------------------------------------------- */

    /**
     * Deliveries per week (completions recorded in the trail).
     *
     * Not "how many are done": how many closed that week. A task reopened and
     * completed again counts again - the flow delivered
     * twice.
     */
    private function throughput()
    {
        $weeks = self::THROUGHPUT_WEEKS;
        $first = $this->today->copy()->startOfWeek()->subWeeks($weeks - 1);

        $labels = [];
        $values = [];

        for ($i = 0; $i < $weeks; $i++) {
            $start = $first->copy()->addWeeks($i);
            $end = $start->copy()->addWeek();

            $count = 0;
            foreach ($this->completions as $doneAt) {
                if ($doneAt->gte($start) && $doneAt->lt($end)) {
                    $count++;
                }
            }

            $labels[] = $start->format('M j');
            $values[] = $count;
        }

        $total = array_sum($values);

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => $total,
            'per_week' => round($total / $weeks, 1),
            'best' => $values ? max($values) : 0,
        ];
    }

    /* ---------------------------------------------------------------------
     | WIP aging
     * ------------------------------------------------------------------- */

    /**
     * How long the open work has been sitting.
     *
     * The management question isn't "how many tasks" but "what is too old to
     * be true". Age is an honest proxy for risk: a task open for 45 days is
     * almost never really "in progress".
     */
    private function aging()
    {
        $buckets = [
            ['label' => 'up to 7 days', 'count' => 0, 'limit' => 7],
            ['label' => '8 to 30 days', 'count' => 0, 'limit' => 30],
            ['label' => '31 to 90 days', 'count' => 0, 'limit' => 90],
            ['label' => 'over 90 days', 'count' => 0, 'limit' => null],
        ];

        $oldest = [];
        $open = 0;
        $stalled = 0;

        foreach ($this->tasks as $task) {
            if ($task->status === 'done') {
                continue;
            }

            $open++;

            $age = $task->created_at
                ? (int) $task->created_at->copy()->startOfDay()->diffInDays($this->today)
                : 0;

            if ($age <= 7) {
                $buckets[0]['count']++;
            } elseif ($age <= 30) {
                $buckets[1]['count']++;
            } elseif ($age <= 90) {
                $buckets[2]['count']++;
            } else {
                $buckets[3]['count']++;
            }

            if ($age > 30) {
                $stalled++;
            }

            $oldest[] = [
                'id' => $task->id,
                'title' => $task->title,
                'age' => $age,
                'status' => $task->status,
                'status_label' => $task->statusLabel(),
            ];
        }

        // Oldest first: that's the list the person will tackle.
        usort($oldest, function ($a, $b) {
            return $b['age'] - $a['age'];
        });

        $max = 0;
        foreach ($buckets as $bucket) {
            $max = max($max, $bucket['count']);
        }

        return [
            'buckets' => $buckets,
            'max' => max(1, $max),
            'open' => $open,
            'stalled' => $stalled,
            'oldest' => array_slice($oldest, 0, 3),
        ];
    }

    /* ---------------------------------------------------------------------
     | Lead time / cycle time
     * ------------------------------------------------------------------- */

    /**
     * Lead time = creation -> completion. Cycle time = work start ->
     * completion. The difference between the two is queue time, which is
     * exactly what a migration committee wants to reduce.
     */
    private function times()
    {
        $lead = [];
        $cycle = [];

        foreach ($this->tasks as $task) {
            if (!$task->created_at) {
                continue;
            }

            $timeline = isset($this->timeline[$task->id]) ? $this->timeline[$task->id] : [];
            $startedAt = null;
            $completedAt = null;

            foreach ($timeline as $point) {
                if ($point['status'] === 'doing' && $startedAt === null) {
                    $startedAt = $point['ts'];
                }

                if ($point['status'] === 'done') {
                    $completedAt = $point['ts'];
                }
            }

            if ($completedAt === null) {
                continue;
            }

            $lead[] = $this->minutes($task->created_at, $completedAt) / 1440;

            if ($startedAt !== null) {
                $cycle[] = $this->minutes($startedAt, $completedAt) / 1440;
            }
        }

        $leadStats = $this->distribution($lead);
        $cycleStats = $this->distribution($cycle);

        // Flow efficiency: how much of the total time was actual work.
        // Below ~25% almost all of the lead time is queue, not execution.
        $efficiency = null;
        if ($leadStats['avg'] > 0 && $cycleStats['avg'] !== null) {
            $efficiency = (int) round(min(100, $cycleStats['avg'] / $leadStats['avg'] * 100));
        }

        return [
            'lead' => $leadStats,
            'cycle' => $cycleStats,
            'efficiency' => $efficiency,
        ];
    }

    private function minutes(Carbon $from, Carbon $to)
    {
        // diffInMinutes returns an integer (absolute) in Carbon 2, so rounding
        // here loses nothing relevant for a metric in days.
        return (int) $from->diffInMinutes($to);
    }

    /**
     * Nearest-rank percentile: p85 is the value below which 85% of the cases
     * fall. Preferred to the mean because a task forgotten for 300 days skews
     * the mean and doesn't skew the p85.
     */
    private function distribution(array $values)
    {
        if (empty($values)) {
            return ['n' => 0, 'p50' => null, 'p85' => null, 'max' => null, 'avg' => null];
        }

        sort($values);
        $count = count($values);

        return [
            'n' => $count,
            'p50' => $this->percentile($values, 0.50),
            'p85' => $this->percentile($values, 0.85),
            'max' => round(max($values), 1),
            'avg' => round(array_sum($values) / $count, 1),
        ];
    }

    private function percentile(array $sorted, $p)
    {
        $index = (int) ceil($p * count($sorted)) - 1;
        $index = max(0, min(count($sorted) - 1, $index));

        return round($sorted[$index], 1);
    }

    /* ---------------------------------------------------------------------
     | Today's snapshot (project header)
     * ------------------------------------------------------------------- */

    /**
     * Where the work is NOW, with no time axis.
     *
     * The CFD tells the historical series; this method tells the baseline,
     * which is what people want when opening the project: how many have left,
     * how many remain and how much of that is late.
     */
    private function snapshot()
    {
        $byStage = array_fill_keys(Task::STATUSES, 0);
        $total = 0;
        $overdue = 0;
        $wip = 0;

        foreach ($this->tasks as $task) {
            $total++;

            if (isset($byStage[$task->status])) {
                $byStage[$task->status]++;
            }

            if ($task->status !== 'done' && $task->isOverdue()) {
                $overdue++;
            }

            if ($task->status === 'doing' || $task->status === 'review') {
                $wip++;
            }
        }

        $done = $byStage['done'];

        return [
            'total' => $total,
            'by_stage' => $byStage,
            'done' => $done,
            'open' => $total - $done,
            'wip' => $wip,
            'overdue' => $overdue,
            // Rounded integer division: 0 tasks can't become 0/0.
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    /**
     * Work per person.
     *
     * Not a ranking: the question is "who is holding what". So the row shows
     * open and done side by side, not a single number that would reward whoever
     * ships more and hide whoever is overloaded.
     */
    private function workload()
    {
        $rows = [];

        foreach ($this->tasks as $task) {
            $id = (int) $task->user_id;

            if (!isset($rows[$id])) {
                $rows[$id] = [
                    'user_id' => $id,
                    'total' => 0,
                    'open' => 0,
                    'done' => 0,
                    'by_stage' => array_fill_keys(Task::STATUSES, 0),
                ];
            }

            $rows[$id]['total']++;

            if (isset($rows[$id]['by_stage'][$task->status])) {
                $rows[$id]['by_stage'][$task->status]++;
            }

            if ($task->status === 'done') {
                $rows[$id]['done']++;
            } else {
                $rows[$id]['open']++;
            }
        }

        // Whoever has the most open work first: that's the queue that needs
        // attention, not alphabetical order.
        usort($rows, function ($a, $b) {
            if ($a['open'] === $b['open']) {
                return $b['done'] - $a['done'];
            }

            return $b['open'] - $a['open'];
        });

        return $rows;
    }

    /* ---------------------------------------------------------------------
     | Summary (top tiles)
     * ------------------------------------------------------------------- */

    private function summary()
    {
        $open = 0;
        $wip = 0;

        foreach ($this->tasks as $task) {
            if ($task->status === 'done') {
                continue;
            }

            $open++;

            if ($task->status === 'doing' || $task->status === 'review') {
                $wip++;
            }
        }

        $last7 = 0;
        $last28 = 0;
        $sevenDaysAgo = $this->today->copy()->subDays(6)->startOfDay();
        $fourWeeksAgo = $this->today->copy()->subWeeks(4)->startOfDay();

        foreach ($this->completions as $doneAt) {
            if ($doneAt->gte($sevenDaysAgo)) {
                $last7++;
            }

            if ($doneAt->gte($fourWeeksAgo)) {
                $last28++;
            }
        }

        $perWeek = round($last28 / 4, 1);

        return [
            'open' => $open,
            'wip' => $wip,
            'done_total' => $this->tasks->count() - $open,
            'last7' => $last7,
            'per_week' => $perWeek,
            // Little's Law: lead time ~ WIP / throughput. With no delivery in the
            // window there is no estimate - returns null and the view omits it.
            'little_days' => $perWeek > 0 ? round($wip / $perWeek * 7, 1) : null,
        ];
    }
}
