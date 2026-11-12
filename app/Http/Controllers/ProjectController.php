<?php

namespace App\Http\Controllers;

use App\Flow\Chart;
use App\Flow\FlowMetrics;
use App\Http\Requests\ProjectRequest;
use App\Project;
use App\Task;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // withCount answers "how many tasks / how many people" with
        // subqueries, instead of an N+1 while looping the list in the view.
        $projects = Project::visibleTo(auth()->user())
            ->withCount(['tasks', 'users', 'tasks as done_tasks_count' => function ($q) {
                $q->where('done', true);
            }])
            ->orderBy('name')
            ->paginate(12);

        // One query for every role on the page, instead of one per card.
        $roles = auth()->user()->projects()
            ->select('projects.id', 'project_user.role')
            ->pluck('role', 'id');

        return view('projects.index', compact('projects', 'roles'));
    }

    public function create()
    {
        return view('projects.create', ['project' => new Project()]);
    }

    public function store(ProjectRequest $request)
    {
        $project = DB::transaction(function () use ($request) {
            $project = new Project($request->validated());
            // user_id is out of $fillable: the owner is the session, not the form.
            $project->user_id = $request->user()->id;
            $project->save();

            // The owner also goes into the pivot: every role check takes a
            // single path, and the member list is right from the start.
            $project->users()->attach($request->user()->id, ['role' => 'owner']);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    /**
     * Project overview: where the work is and how it got here.
     *
     * The page opens on the timeline, not on the task list. The list answers
     * "what do I do now" and already exists at /tasks; here the question is
     * "how has this project moved and how much is left".
     *
     * Everything comes from App\Flow\FlowMetrics scoped to the project: the
     * same calculation as /fluxo, filtered. There is no second metric formula
     * that could drift from the first.
     */
    public function show(Project $project)
    {
        $this->authorize('view', $project);

        $project->load(['owner', 'users']);

        $flow = (new FlowMetrics(auth()->user(), FlowMetrics::DEFAULT_DAYS, $project))->build();

        // Today's progress bar: one band per stage.
        $flow['progress'] = Chart::stack($this->stageSegments($flow['snapshot']['by_stage']), 900, 14);

        // Each week of the timeline gets the same bar, with the state at that
        // moment. Geometry here; the view only paints.
        foreach ($flow['history']['weeks'] as $index => $week) {
            $flow['history']['weeks'][$index]['chart'] = Chart::stack($week['states'], 640, 10);
        }

        // Names of the people in the trail. Someone who left the project
        // still shows up in the past: rewriting history would be lying.
        $names = $project->users->pluck('name', 'id');

        $missing = [];
        foreach ($flow['workload'] as $row) {
            if (!$names->has($row['user_id'])) {
                $missing[] = $row['user_id'];
            }
        }

        if (!empty($missing)) {
            $names = $names->union(User::whereIn('id', $missing)->pluck('name', 'id'));
        }

        // The email comes with the name in the same query: the team list shows
        // both side by side and isn't worth a SELECT per row.
        $emails = $project->users->pluck('email', 'id');

        if (!empty($missing)) {
            $emails = $emails->union(User::whereIn('id', $missing)->pluck('email', 'id'));
        }

        $roster = $project->users->keyBy('id');

        $members = [];
        $seen = [];

        foreach ($flow['workload'] as $row) {
            $seen[(int) $row['user_id']] = true;

            $members[] = [
                'id' => (int) $row['user_id'],
                'name' => $names->get($row['user_id'], '-'),
                'email' => $emails->get($row['user_id']),
                'role' => $this->roleOf($project, $roster, (int) $row['user_id']),
                'open' => $row['open'],
                'done' => $row['done'],
                'total' => $row['total'],
                'chart' => Chart::stack($this->stageSegments($row['by_stage']), 220, 8),
            ];
        }

        // Members with no tasks show up too: an incomplete team is
        // information, not a blank row.
        foreach ($project->users as $user) {
            if (isset($seen[(int) $user->id])) {
                continue;
            }

            $members[] = [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->roleOf($project, $roster, (int) $user->id),
                'open' => 0,
                'done' => 0,
                'total' => 0,
                'chart' => Chart::stack($this->stageSegments([]), 220, 8),
            ];
        }

        if (!isset($seen[(int) $project->user_id]) && !$project->users->contains('id', $project->user_id)) {
            $members[] = [
                'id' => (int) $project->user_id,
                'name' => $project->owner ? $project->owner->name : '-',
                'email' => $project->owner ? $project->owner->email : null,
                'role' => 'owner',
                'open' => 0,
                'done' => 0,
                'total' => 0,
                'chart' => Chart::stack($this->stageSegments([]), 220, 8),
            ];
        }

        return view('projects.show', [
            'project' => $project,
            'role' => $project->roleFor(auth()->user()),
            // The page is not a lister: it shows what is open now and sends the
            // rest to /tasks, which already filters and paginates.
            'openTasks' => $project->tasks()
                ->where('status', '!=', 'done')
                ->with('user')
                ->sorted('priority')
                ->limit(5)
                ->get(),
            'flow' => $flow,
            'snapshot' => $flow['snapshot'],
            'members' => $members,
            'names' => $names,
        ]);
    }

    /**
     * One band per stage, in workflow order. The view decides neither color
     * nor order: it receives the series ready.
     */
    private function stageSegments(array $byStage)
    {
        $labels = Task::statusLabels();
        $segments = [];

        foreach (Task::STATUSES as $stage) {
            $segments[] = [
                'key' => $stage,
                'label' => $labels[$stage],
                'value' => isset($byStage[$stage]) ? (int) $byStage[$stage] : 0,
            ];
        }

        return $segments;
    }

    /**
     * Role without querying the DB again: the pivot is already loaded.
     */
    private function roleOf(Project $project, $roster, $userId)
    {
        if ((int) $project->user_id === (int) $userId) {
            return 'owner';
        }

        $member = $roster->get($userId);

        return $member && $member->pivot ? $member->pivot->role : null;
    }

    /**
     * Project schedule (Gantt style).
     */
    public function timeline(Project $project)
    {
        $this->authorize('view', $project);

        $project->load(['tasks.user']);

        $flow = (new FlowMetrics(auth()->user(), FlowMetrics::DEFAULT_DAYS, $project))->build();

        // Today's progress bar: one band per stage.
        $flow['progress'] = Chart::stack($this->stageSegments($flow['snapshot']['by_stage']), 900, 14);

        // Scale window: days on each side of today and the tick step.
        // Whitelist: a free-form ?zoom= doesn't pick a code path.
        $windows = [
            'day' => ['half' => 7, 'step' => 1],
            'week' => ['half' => 28, 'step' => 7],
            'month' => ['half' => 84, 'step' => 14],
        ];
        // is_string: ?zoom[]=x would send an array, and the warning would become a 500.
        $zoom = request('zoom');
        $zoom = is_string($zoom) && isset($windows[$zoom]) ? $zoom : 'week';
        $half = $windows[$zoom]['half'];
        $step = $windows[$zoom]['step'];

        $today = now()->startOfDay();
        $scaleStart = $today->copy()->subDays($half);
        $scaleEnd = $today->copy()->addDays($half);
        $spanDays = $half * 2;

        $labels = [];
        for ($i = 0; $i <= $spanDays; $i += $step) {
            $labels[] = [
                'label' => $scaleStart->copy()->addDays($i)->format('M j'),
                'left' => round($i / $spanDays * 100, 2),
            ];
        }

        // SIGNED position: diffInDays($x, false) is negative before the start
        // of the scale. With the absolute value (Carbon's default), a task
        // from 3 months ago was drawn in the future.
        $tasks = [];
        $hidden = 0;
        $sorted = $project->tasks->sortBy(function ($task) {
            return $task->created_at ? $task->created_at->timestamp : 0;
        });

        foreach ($sorted as $task) {
            if (!$task->created_at) {
                continue;
            }

            $start = $task->created_at->copy()->startOfDay();
            $end = $task->due_date ? $task->due_date->copy()->startOfDay() : $start;

            if ($end->lt($start)) {
                $end = $start;
            }

            $startRel = $scaleStart->diffInDays($start, false);
            $endRel = $scaleStart->diffInDays($end, false);

            if ($endRel < 0 || $startRel > $spanDays) {
                $hidden++;
                continue;
            }

            $from = max(0, $startRel);
            $to = min($spanDays, $endRel);

            $tasks[] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'status_label' => $task->statusLabel(),
                'owner' => $task->user->name ?? '-',
                'due' => $task->due_date ? $task->due_date->format('M j') : null,
                'overdue' => $task->isOverdue(),
                'left' => round($from / $spanDays * 100, 2),
                // Minimum width so a one-day task stays visible.
                'width' => round(max(1, $to - $from + 1) / $spanDays * 100, 2),
                'cut_start' => $startRel < 0,
                'cut_end' => $endRel > $spanDays,
            ];
        }

        return view('projects.timeline', [
            'project' => $project,
            'role' => $project->roleFor(auth()->user()),
            'tasks' => $tasks,
            'hidden' => $hidden,
            'flow' => $flow,
            'scaleStart' => $scaleStart,
            'scaleEnd' => $scaleEnd,
            'scalePoints' => $labels,
            'todayLeft' => round($half / $spanDays * 100, 2),
            'zoom' => $zoom,
        ]);
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(ProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    /**
     * Deleting a project does NOT delete its tasks.
     *
     * On MySQL the FK is onDelete('set null'); on the test SQLite the ALTER
     * TABLE ADD CONSTRAINT is ignored, so the explicit update guarantees the
     * same behavior on both. A project task becomes a personal task of its
     * owner (project_id NULL), with its history intact.
     */
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        DB::transaction(function () use ($project) {
            $project->tasks()->update(['project_id' => null]);
            $project->users()->detach();
            $project->delete();
        });

        return redirect()->route('projects.index')->with('status', 'Project deleted. Its tasks became personal.');
    }

    public function addMember(Request $request, Project $project)
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:member,viewer',
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user->id === $project->user_id) {
            return back()->withErrors(['email' => 'This person already owns the project.']);
        }

        // syncWithoutDetaching: inviting again updates the role instead of
        // violating unique(project_id, user_id).
        $project->users()->syncWithoutDetaching([
            $user->id => ['role' => $data['role']],
        ]);

        return back()->with('status', 'Member added as ' . $data['role'] . '.');
    }

    public function removeMember(Project $project, $userId)
    {
        $this->authorize('manageMembers', $project);

        if ((int) $userId === (int) $project->user_id) {
            abort(422, 'The owner cannot be removed from the project.');
        }

        $project->users()->detach((int) $userId);

        return back()->with('status', 'Member removed.');
    }
}
