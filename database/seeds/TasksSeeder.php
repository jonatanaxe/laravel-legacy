<?php

use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Realistic load: the dashboard needs data to be worth looking at.
 *
 * Two things matter here:
 *
 * 1. **Due dates spread** across the past, today and the future - that is
 *    what makes the overdue counter and the due-date sort show something.
 *
 * 2. **Real history.** The flow page (CFD, lead time, WIP aging) only has
 *    something to measure if there is a trail in `task_events`. Instead of
 *    inventing a chart series, the seed takes the real path: it creates the
 *    task and moves it through stages with `$task->status = ...;
 *    $task->save()`, letting the TaskObserver record the event. Only the
 *    clock changes: `Carbon::setTestNow()` makes `now()` return the date of
 *    the time, so created_at and the events are backdated.
 *
 *    In other words: the data is for demo purposes, but the chart series is
 *    produced by the same code that runs in production - there is no
 *    parallel path that could lie differently from the app.
 *
 * Stages per row: the number of days in `stages` sets how far the task went
 * (Task::STATUSES[count(stages)]). 0 = backlog, 1 = in progress, 2 = review,
 * 3 = done.
 *
 * firstOrCreate by title: running the seeder twice duplicates neither tasks
 * nor audit events.
 */
class TasksSeeder extends Seeder
{
    public function run()
    {
        $ana = App\User::where('email', 'ana@example.com')->first();
        $bruno = App\User::where('email', 'bruno@example.com')->first();

        if (!$ana || !$bruno) {
            return;
        }

        $project = App\Project::where('name', ProjectsSeeder::NAME)->first();

        // Columns: title, description, priority, due (days from today),
        // created (days ago), stages (days after creation).
        $anaTasks = [
            ['Study PHP 8', 'Rector, deprecations and the version jump', 'high', -10, 20, [1, 3, 5]],
            ['Inventory Composer dependencies', 'List what blocks the jump with why-not', 'high', -20, 24, [2, 9, 12]],
            ['Replace fzaninotto/faker', 'Abandoned package; switch to fakerphp/faker', 'high', -3, 12, [2, 5]],
            ['Map laravelcollective/html usage', 'Each helper becomes a Blade component or spatie/laravel-html', 'high', 0, 9, [1]],
            ['Write characterization tests for checkout', 'Without them, every step is a gamble', 'high', -1, 15, []],
            ['Review abandoned dependencies', 'Two known ones: faker and html', 'high', -2, 11, [1]],
            ['Plan the deploy window', 'One step per deploy, with the rollback ready', 'high', 1, 6, []],
            ['Validate extensions in the Dockerfile', 'mbstring, pdo, openssl in the new image', 'high', 2, 5, [1]],
            ['Set up CI with phpunit', 'Mandatory gate from the first step on', 'high', -20, 28, [3, 6, 8]],
            ['Add the edge suite to the pipeline', 'The command already exits with an error code', 'normal', -15, 4, [1, 2, 3]],
            ['Review authorization policies', 'Compare the owner with ===, never with ==', 'high', -12, 16, [1, 4, 6]],
            ['Upgrade to PHP 7.4 (Laravel 6)', 'First step of the ladder', 'high', 5, 4, []],
            ['Freeze behavior with snapshot tests', 'Snapshot before touching any dependency', 'high', 6, 3, []],
            ['Run rector in dry-run mode', 'See the diff before applying', 'normal', 7, 3, [1]],
            ['Replace the removed array_get helper', 'Move to Arr::get', 'normal', 8, 2, []],
            ['Remove str_contains calls without a polyfill', 'Symfony polyfill or our own function', 'normal', 9, 2, []],
            ['Upgrade phpunit 7.5 to 8', 'Follows the framework jump', 'normal', 12, 8, [2]],
            ['Migrate password hashing', 'Gradual rehash on login', 'normal', 15, 7, []],
            ['Measure N+1 on the task list', 'Eager load events and user', 'normal', 3, 10, [1, 2]],
            ['Document rollback per step', 'Every step needs a plan B', 'normal', 4, 13, [3, 4]],
            ['Switch the session driver to redis', 'File sessions do not scale horizontally', 'low', 21, 30, []],
            ['Adjust the CSP to remove unsafe-inline', 'Inline styles are the last to go', 'low', 25, 35, [2]],
            ['Audit access logs', 'IP retention and masking', 'low', 30, 45, []],
            ['Train the team on PHP 8.1', 'ReturnTypeWillChange and types', 'low', 40, 40, []],
            ['Review error messages per locale', 'The en fallback must not leak translation keys', 'low', -5, 21, [2, 7, 11]],

            // History from the discovery phase: it exists so the flow has a
            // 60-90 day series, not for the day-to-day list. All closed.
            ['Map the legacy routes', 'How many routes, how many with logic in the controller', 'normal', -60, 82, [2, 6, 9]],
            ['Map jobs and crons', 'What runs outside the request and nobody remembers', 'normal', -55, 76, [1, 4, 7]],
            ['List controllers with business logic', 'Logic in a controller becomes a service before the upgrade', 'normal', -52, 72, [3, 8, 11]],
            ['Freeze the database schema', 'Versioned dump before any migration', 'high', -50, 68, [1, 3, 5]],
            ['Catalog laravelcollective helpers', 'Each helper becomes a Blade component', 'normal', -48, 63, [2, 9, 13]],
            ['Run phpcs on the legacy code', 'A measurable starting point, not taste', 'low', -45, 58, [1, 2, 4]],
            ['Measure application boot time', 'Baseline to compare with PHP 8', 'normal', -40, 52, [3, 7, 10]],
            ['Set up Xdebug in the container', 'Without a debugger, every step is guesswork', 'low', -38, 46, [1, 5, 6]],
            ['Write the step 1 ADR', 'The decision gets recorded, not kept in someone\'s head', 'normal', -35, 38, [2, 4, 6]],
            ['Review dependency licenses', 'MIT, BSD and one forgotten GPL', 'normal', -30, 31, [1, 3, 5]],
            ['Automate backups before the upgrade', 'Rollback only exists if the backup exists', 'high', -28, 24, [2, 5, 7]],
            ['Validate uploads on a private disk', 'Files outside the document root', 'normal', -25, 5, [1, 2, 3]],
        ];

        $brunoTasks = [
            ['My task', 'Exists to prove Bruno cannot see Ana\'s', 'normal', 2, 5, []],
            ['Review a teammate\'s PR', 'Different context, same app', 'low', 6, 4, [1]],
            ['Set up the local environment', 'Clone, start madock, migrate --seed', 'normal', -4, 18, [1, 3, 4]],
            ['Read docs/security.md', 'The defenses and how to attack each one', 'low', 10, 3, []],
        ];

        // The migration work belongs to the project - that is where the
        // overview, the timeline and the flow have something to show. What
        // stays personal is just enough to prove both scopes coexist on the
        // same screen without leaking into each other.
        $anaPersonal = [
            // 'policies' is the personal task the HTTP suite uses to prove
            // IDOR: if it moved into the project, Bruno would see it and the
            // test would lose its point.
            'Review authorization policies',
            'Train the team on PHP 8.1',
            'Audit access logs',
            'Review error messages per locale',
        ];

        $anaProject = array_values(array_diff(array_column($anaTasks, 0), $anaPersonal));

        $brunoProject = [
            'Review a teammate\'s PR',
            'Set up the local environment',
        ];

        // Reference date taken ONCE, before touching the clock: if it were
        // read inside seedTasks, the second call would inherit the fake
        // `now()` of the first call's last task.
        $anchor = Carbon::today();

        // The clock goes back to normal even if something blows up midway: a
        // seeder that leaves `now()` stuck in the past breaks everyone after.
        try {
            $this->seedTasks($ana, $anchor, $anaTasks, $anaProject, $project);
            $this->seedTasks($bruno, $anchor, $brunoTasks, $brunoProject, $project);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Creates each task on its backdated date and walks it through the
     * stages with the real code - the observer records the trail and the CFD
     * has something to read.
     */
    private function seedTasks(App\User $user, Carbon $anchor, array $rows, array $projectTitles, $project)
    {
        foreach ($rows as $row) {
            list($title, $description, $priority, $dueOffset, $createdDaysAgo, $stages) = $row;

            Carbon::setTestNow($anchor->copy()->subDays($createdDaysAgo)->setTime(9, 0));

            $task = $user->tasks()->firstOrCreate(
                ['title' => $title],
                [
                    'description' => $description,
                    'priority' => $priority,
                    'due_date' => $anchor->copy()->addDays($dueOffset)->toDateString(),
                    'status' => 'backlog',
                ]
            );

            // It already existed: don't rewrite history or duplicate events.
            if (!$task->wasRecentlyCreated) {
                continue;
            }

            // project_id is not fillable (on purpose), so it is assigned
            // here. withoutEvents avoids an "edited" that never happened.
            if ($project && in_array($title, $projectTitles, true)) {
                App\Task::withoutEvents(function () use ($task, $project) {
                    $task->project_id = $project->id;
                    $task->save();
                });
            }

            $createdAt = $task->created_at->copy();

            foreach ($stages as $index => $daysAfter) {
                $when = $createdAt->copy()->addDays($daysAfter)->setTime(11, 30);

                // Never record an event in the future: if the stage would
                // fall after today, the task simply hasn't got there yet.
                if ($when->gt($anchor)) {
                    break;
                }

                Carbon::setTestNow($when);
                $task->status = App\Task::STATUSES[$index + 1];
                $task->save();
            }
        }
    }
}
