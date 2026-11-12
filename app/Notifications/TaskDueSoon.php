<?php

namespace App\Notifications;

use App\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Due-date reminder.
 *
 * implements ShouldQueue: the caller doesn't wait for delivery. The database
 * channel turns the reminder into a record in the app, visible and markable
 * as read.
 *
 * $tries and $timeout are explicit: they keep a stuck job from holding the
 * worker forever, and send the job to failed_jobs after 3 errors.
 */
class TaskDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 30;

    private $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $due = $this->task->due_date;

        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'due_date' => $due ? $due->toDateString() : null,
            'message' => $this->message($due),
        ];
    }

    private function message($due)
    {
        if ($due === null) {
            return 'Task has no due date.';
        }

        if ($due->isPast() && !$due->isToday()) {
            return 'Overdue since ' . $due->format('M j, Y') . '.';
        }

        if ($due->isToday()) {
            return 'Due today.';
        }

        return 'Due tomorrow (' . $due->format('M j, Y') . ').';
    }
}
