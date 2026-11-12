<?php

namespace App\Console\Commands;

use App\Notifications\TaskDueSoon;
use App\Task;
use Illuminate\Console\Command;

/**
 * Scans tasks with an upcoming (or past) due date and notifies the owner.
 *
 * Two guarantees:
 *  1. idempotency: the "claim" is a conditional UPDATE
 *        UPDATE tasks SET reminded_at = now()
 *        WHERE id = ? AND reminded_at IS NULL
 *     Only whoever changed 1 row sends. Two workers at the same time don't
 *     duplicate the reminder - this is not a check-then-act in PHP, which
 *     would race;
 *  2. the audit trail stays clean: the update goes through the query
 *     builder, so the observer doesn't record "edited the task". A reminder
 *     is not a business change.
 *
 * Window: overdue + today + tomorrow, open and never reminded.
 */
class SendDueSoonReminders extends Command
{
    protected $signature = 'tasks:due-soon
                            {--dry-run : Show how many tasks would be reminded, without sending or marking}';

    protected $description = 'Sends a reminder for open tasks due by tomorrow';

    public function handle()
    {
        $limit = now()->addDay()->toDateString();

        $query = Task::query()
            ->where('done', false)
            ->whereNotNull('due_date')
            // whereDate, not <= 'Y-m-d': on SQLite the column stores
            // '2026-11-12 00:00:00' and a string comparison with '2026-11-12'
            // would say tomorrow's deadline is in the future. whereDate
            // compares the date on both drivers (strftime on SQLite, DATE()
            // on MySQL).
            ->whereDate('due_date', '<=', $limit)
            ->whereNull('reminded_at');

        if ($this->option('dry-run')) {
            $this->info($query->count() . ' task(s) would be reminded.');

            return 0;
        }

        $sent = 0;

        $query->orderBy('id')->chunkById(200, function ($tasks) use (&$sent) {
            foreach ($tasks as $task) {
                // Atomic claim: whoever updates 1 row earns the right to send.
                // The whereNull is the idempotency.
                $claimed = Task::where('id', $task->id)
                    ->whereNull('reminded_at')
                    ->update(['reminded_at' => now()]);

                if ($claimed !== 1) {
                    continue;
                }

                $task->user->notify(new TaskDueSoon($task));
                $sent++;
            }
        });

        $this->info($sent . ' reminder(s) sent.');

        return 0;
    }
}
