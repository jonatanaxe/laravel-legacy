<?php

namespace App\Observers;

use App\Task;
use App\TaskEvent;

/**
 * Audit trail without polluting the controller.
 *
 * The observer listens to Eloquent's created/updated/deleted: any path that
 * writes a task (web, API, tinker, seeder) produces an event. If the logging
 * lived in the controller, the API and artisan would be left out.
 *
 * `updated` only records when something relevant changed (isDirty) - no
 * "edited" event for a save that changed nothing.
 */
class TaskObserver
{
    public function created(Task $task)
    {
        $this->log($task, 'created', [
            'title' => $task->title,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_date' => $task->due_date ? $task->due_date->toDateString() : null,
        ]);
    }

    public function updated(Task $task)
    {
        // version is concurrency-control noise, not a business change: it
        // stays out of the trail (otherwise every save becomes "edited").
        $changed = array_values(array_diff(array_keys($task->getDirty()), ['version']));

        if (empty($changed)) {
            return;
        }

        // `done` mirrors `status` (see the model mutators). Reporting both
        // would be a duplicated diff of the same fact.
        if (in_array('status', $changed, true)) {
            $changed = array_values(array_diff($changed, ['done']));
        }

        // A stage transition gets its own name in the trail: whoever reads the
        // history wants "completed" or "moved to review", not "edited done
        // 0 -> 1". The name is also what the CFD consumes: 'completed' closes
        // the cycle.
        if ($task->isDirty('status')) {
            $from = $task->getOriginal('status');
            $to = $task->status;

            if ($to === 'done') {
                $action = 'completed';
            } elseif ($from === 'done') {
                $action = 'reopened';
            } else {
                $action = 'status_changed';
            }
        } elseif ($task->isDirty('done')) {
            // Defensive path: someone wrote the done column without going
            // through the mutator (query builder). The trail is still right.
            $action = $task->done ? 'completed' : 'reopened';
        } else {
            $action = 'updated';
        }

        $diff = [];
        foreach ($changed as $field) {
            $diff[$field] = [
                'from' => $task->getOriginal($field),
                'to' => $task->getAttribute($field),
            ];
        }

        $this->log($task, $action, ['changed' => $diff]);
    }

    public function deleted(Task $task)
    {
        // SoftDeletes' forceDelete calls delete() internally, which fires
        // 'deleted' again. Without this guard a permanent delete would record
        // "deleted" twice - one of them a lie.
        if ($task->isForceDeleting()) {
            return;
        }

        // task_id stays recorded after the delete: the event is the evidence.
        $this->log($task, 'deleted', ['title' => $task->title]);
    }

    public function restored(Task $task)
    {
        $this->log($task, 'restored', ['title' => $task->title]);
    }

    public function forceDeleted(Task $task)
    {
        $this->log($task, 'force_deleted', ['title' => $task->title]);
    }

    private function log(Task $task, $action, array $meta)
    {
        // auth()->id() on a web request; seeders/console have no session, so
        // it falls back to the task owner (a seeded task still has an author).
        TaskEvent::create([
            'task_id' => $task->id,
            'user_id' => auth()->id() ?: $task->user_id,
            'action' => $action,
            'meta' => json_encode($meta),
        ]);
    }
}
