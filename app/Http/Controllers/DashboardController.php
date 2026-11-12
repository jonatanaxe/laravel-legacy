<?php

namespace App\Http\Controllers;

use App\Legacy\Billing\InvoiceRecord;
use App\Project;
use App\Task;
use App\TaskEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard: the first screen after login. Answers "what do I do today?" and
 * "how is the team doing?" without opening five screens.
 *
 * Each block is ONE query, always through the visibleTo scope - the dashboard
 * sees nothing the list wouldn't.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        // Counters: a single selectRaw, like the task list summary.
        $counts = Task::visibleTo($user)
            ->selectRaw('SUM(CASE WHEN done = 0 THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN done = 0 AND DATE(due_date) < ? THEN 1 ELSE 0 END) as overdue_count', [$today->toDateString()])
            ->selectRaw('SUM(CASE WHEN done = 0 AND DATE(due_date) >= ? AND DATE(due_date) <= ? THEN 1 ELSE 0 END) as week_count', [
                $today->toDateString(), $today->copy()->addDays(7)->toDateString(),
            ])
            ->first();

        // My week: overdue first, then by due date.
        $week = Task::visibleTo($user)
            ->with('project')
            ->where('done', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(7))
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $projects = Project::visibleTo($user)
            ->withCount(['tasks', 'tasks as done_tasks_count' => function ($q) {
                $q->where('done', true);
            }])
            ->orderBy('name')
            ->limit(4)
            ->get();

        // Recent activity of what I can see. whereHas with the same scope:
        // events of other people's tasks don't leak through the feed.
        $recent = TaskEvent::with(['user', 'task'])
            ->whereHas('task', function ($q) use ($user) {
                $q->visibleTo($user);
            })
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get();

        // Billing this month: sum per status, mapped by the new code.
        $billing = ['Issued' => 0.0, 'Draft' => 0.0];
        $rows = DB::table('legacy_invoices')
            ->select('status', DB::raw('SUM(total) as amount'))
            ->where('issued_at', '>=', $today->copy()->startOfMonth())
            ->groupBy('status')
            ->get();
        foreach ($rows as $row) {
            $name = InvoiceRecord::statusNameFor($row->status);
            if (isset($billing[$name])) {
                $billing[$name] += (float) $row->amount;
            }
        }

        return view('dashboard.index', [
            'user' => $user,
            'today' => $today,
            'counts' => [
                'open' => (int) $counts->open_count,
                'overdue' => (int) $counts->overdue_count,
                'week' => (int) $counts->week_count,
                'unread' => $user->unreadNotifications()->count(),
            ],
            'week' => $week,
            'projects' => $projects,
            'recent' => $recent,
            'billing' => $billing,
        ]);
    }
}
