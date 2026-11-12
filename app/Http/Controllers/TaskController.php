<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Notifications\TaskDueSoon;
use App\Project;
use App\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Every route requires an authenticated session (defense in depth: the
     * middleware lives in the controller, not only on the route).
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(IndexTaskRequest $request)
    {
        $filters = $request->filters();

        // Composite scope: my personal tasks + tasks of the projects I belong
        // to. The rule lives in the model (scopeVisibleTo), not in the
        // controller - the API and the dashboard use the same one.
        $query = Task::visibleTo($request->user())
            ->with('project') // avoids N+1 when showing the project name
            ->state($filters['status'])
            ->search($filters['q'])
            ->sorted($filters['sort']);

        // appends() keeps the filters in pagination links; empty values don't
        // need to travel in the URL.
        $tasks = $query->paginate(10)->appends(array_filter($filters, function ($value) {
            return $value !== '' && $value !== null;
        }));

        // One query for the whole summary. Four separate COUNTs would cost
        // four round-trips for the same data.
        $stats = $this->stats($request->user());

        return view('tasks.index', compact('tasks', 'stats', 'filters'));
    }

    public function create(Request $request)
    {
        // Empty instance: the view needs to know if it is create or edit.
        // Only projects where the user can write go into the select - viewers
        // can't create tasks, and the policy confirms it on store.
        return view('tasks.create', [
            'task' => new Task(),
            'projects' => $request->user()->writableProjects()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTaskRequest $request)
    {
        $task = new Task($request->validatedDefaults());

        // project_id is NOT in $fillable: if the client sends someone else's
        // project, fill() drops it. The server decides, after checking the
        // role.
        if ($request->filled('project_id')) {
            $project = Project::findOrFail($request->input('project_id'));
            $this->authorize('createTask', $project);
            $task->project_id = $project->id;
        }

        // Owner set through the session's relationship, never from the payload.
        $request->user()->tasks()->save($task);

        return redirect()->route('tasks.index')->with('status', 'Task created.');
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        // The trail is loaded with each event's user (no N+1).
        $task->load(['events.user', 'attachments.user']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task)
    {
        $this->authorize('update', $task);

        return view('tasks.edit', compact('task'));
    }

    /**
     * Update with optimistic locking and a transaction.
     *
     * Two guarantees a plain update doesn't give:
     * 1) concurrency: the version the form read travels in the hidden
     *    `version` field. If the row has changed since, the server answers
     *    409 instead of overwriting someone else's work;
     * 2) atomicity: the task change and the audit event (written by the
     *    observer) happen in the same transaction - there is never a
     *    "changed" without a trail.
     */
    public function update(StoreTaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        $stale = false;

        DB::transaction(function () use ($request, $task, &$stale) {
            // Re-reading the row inside the transaction (and, on MySQL, with
            // FOR UPDATE) closes the window between "checked the version" and
            // "wrote".
            $fresh = Task::whereKey($task->getKey())->lockForUpdate()->first();

            if ($fresh === null) {
                $stale = true;

                return;
            }

            // (int) on purpose: a missing value becomes 0, never null == '0'.
            if ((int) $request->input('version', 0) !== (int) $fresh->version) {
                $stale = true;

                return;
            }

            // version stays out of $fillable: the server decides, not the
            // form. Direct assignment, no mass assignment.
            $fresh->version = $fresh->version + 1;

            // The form's project_id is ignored here on purpose: moving a task
            // between projects needs authorization on both sides and is not
            // part of the workflow. fill() drops it because it isn't fillable.
            $fresh->update($request->validatedDefaults());
        });

        if ($stale) {
            abort(409, 'This task was changed in another session. Reload the page.');
        }

        return redirect()->route('tasks.index')->with('status', 'Task updated.');
    }

    /**
     * Soft delete: the row leaves the lists, the audit trail stays.
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')->with('status', 'Task moved to trash.');
    }

    /**
     * Trash: only deleted tasks the user can see.
     */
    public function trash()
    {
        $tasks = Task::visibleTo(auth()->user())
            ->onlyTrashed()
            ->with('project')
            ->latest('deleted_at')
            ->paginate(10);

        return view('tasks.trash', ['tasks' => $tasks]);
    }

    /**
     * Restore from trash.
     *
     * No route model binding withTrashed() in 5.8: implicit binding applies
     * the SoftDeletes scope and would return 404. The lookup is explicit.
     */
    public function restore($id)
    {
        $task = Task::withTrashed()->findOrFail($id);

        $this->authorize('restore', $task);

        $task->restore();

        return redirect()->route('tasks.index')->with('status', 'Task restored.');
    }

    /**
     * Permanent delete. The trail in task_events survives (no FK to tasks).
     */
    public function forceDelete($id)
    {
        $task = Task::withTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $task);

        $task->forceDelete();

        return redirect()->route('tasks.trash')->with('status', 'Task permanently deleted.');
    }

    /**
     * On-demand reminder: same notification as the scheduled command.
     *
     * The route exists for two things: to prove the queue path in a
     * 4-minute demo (without waiting for cron) and to give users a "remind
     * me". It doesn't touch reminded_at: the scheduled run has its own rule.
     */
    public function remind(Task $task)
    {
        $this->authorize('update', $task);

        $task->user->notify(new TaskDueSoon($task));

        return back()->with('status', 'Reminder sent to ' . $task->user->name . '.');
    }

    /**
     * Summary aggregates in a single query.
     *
     * CASE WHEN is the portable way to do four counts at once: it works the
     * same on MySQL and SQLite, with no subquery per metric.
     */
    private function stats($user)
    {
        return Task::visibleTo($user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN done = 0 THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN done = 1 THEN 1 ELSE 0 END) as done_count')
            ->selectRaw(
                'SUM(CASE WHEN done = 0 AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_count',
                [now()->toDateString()]
            )
            ->first();
    }
}
