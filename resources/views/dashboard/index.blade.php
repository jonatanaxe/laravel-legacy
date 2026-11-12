@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $money = function ($value) {
            return 'R$ ' . number_format((float) $value, 2, '.', ',');
        };
        $firstName = explode(' ', $user->name)[0];
    @endphp

    <div class="wrap page">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-muted">{{ $today->format('l, F j') }}</p>
                <h1 class="text-3xl mt-1">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="text-body mt-1">
                    @if ($counts['overdue'])
                        You have <strong class="text-danger">{{ $counts['overdue'] }} overdue {{ $counts['overdue'] > 1 ? 'tasks' : 'task' }}</strong>
                        and {{ $counts['week'] }} due in the next 7 days.
                    @elseif ($counts['week'])
                        Nothing overdue. {{ $counts['week'] }} {{ $counts['week'] > 1 ? 'tasks are' : 'task is' }} due in the next 7 days.
                    @else
                        Nothing overdue and nothing due this week.
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a class="btn btn-outline btn-sm" href="{{ route('imports.index') }}">Import CSV</a>
                <a class="btn btn-primary btn-sm" href="{{ route('tasks.create') }}">+ New task</a>
            </div>
        </header>

        <section class="stats mt-6" aria-label="Summary">
            <a class="stat hover:no-underline" href="{{ route('tasks.index', ['status' => 'open']) }}">
                <div class="stat-label">Open</div>
                <div class="stat-value">{{ $counts['open'] }}</div>
                <div class="stat-note">see list →</div>
            </a>
            <a class="stat {{ $counts['overdue'] ? 'is-danger' : '' }} hover:no-underline" href="{{ route('tasks.index', ['status' => 'overdue']) }}">
                <div class="stat-label">Overdue</div>
                <div class="stat-value">{{ $counts['overdue'] }}</div>
                <div class="stat-note">{{ $counts['overdue'] ? 'past due date' : 'all on time' }}</div>
            </a>
            <div class="stat">
                <div class="stat-label">Due in 7 days</div>
                <div class="stat-value">{{ $counts['week'] }}</div>
                <div class="stat-note">open with a due date</div>
            </div>
            <a class="stat hover:no-underline" href="{{ route('notifications.index') }}">
                <div class="stat-label">Reminders</div>
                <div class="stat-value">{{ $counts['unread'] }}</div>
                <div class="stat-note">unread</div>
            </a>
        </section>

        <div class="split">
            <div class="flex flex-col gap-4 min-w-0">
                {{-- My week --}}
                <section class="panel" aria-labelledby="week-title">
                    <div class="panel-pad pb-3 flex items-baseline justify-between gap-3 border-b border-line">
                        <h2 class="panel-title" id="week-title">My week</h2>
                        <a class="text-sm" href="{{ route('tasks.index', ['sort' => 'due', 'status' => 'open']) }}">All by due date →</a>
                    </div>

                    <ul class="list-none m-0 p-0">
                        @forelse ($week as $task)
                            @php $late = $task->isOverdue(); @endphp
                            <li class="flex items-center gap-4 px-6 py-3 border-b border-line">
                                <div class="w-12 flex-shrink-0 text-center rounded-md py-1 {{ $late ? 'bg-danger-soft text-danger' : 'bg-surface-2 text-ink' }}">
                                    <div class="text-lg font-semibold leading-none">{{ $task->due_date->format('d') }}</div>
                                    <div class="text-xs uppercase">{{ $task->due_date->format('M') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a class="block truncate font-semibold text-ink hover:text-brand" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                    <p class="text-xs text-muted truncate">
                                        {{ $task->project->name ?? 'personal' }}
                                        @if ($late) · <span class="text-danger font-semibold">overdue</span>@endif
                                    </p>
                                </div>
                                <span class="badge {{ $task->statusBadge() }}">{{ $task->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-sm text-muted">Nothing due in the next 7 days.</li>
                        @endforelse
                    </ul>
                </section>

                {{-- Recent activity --}}
                <section class="panel panel-pad" aria-labelledby="recent-title">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="panel-title" id="recent-title">Recent activity</h2>
                        <a class="text-sm" href="{{ route('flow.index') }}">See flow →</a>
                    </div>

                    <ul class="list-none m-0 p-0 mt-3">
                        @forelse ($recent as $event)
                            <li class="flex items-start gap-3 py-2">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-surface-3 text-xs font-semibold text-ink">
                                    {{ mb_strtoupper(mb_substr($event->user->name ?? 'S', 0, 1)) }}
                                </span>
                                <div class="min-w-0 text-sm">
                                    <p class="text-body">
                                        <strong class="text-ink">{{ $event->user->name ?? 'system' }}</strong>
                                        {{ $event->label() }}
                                        <a href="{{ route('tasks.show', $event->task_id) }}">{{ $event->task->title }}</a>
                                    </p>
                                    <p class="text-xs text-muted">{{ $event->created_at ? $event->created_at->diffForHumans() : '' }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-muted">No activity yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            <aside class="split-aside">
                {{-- Projects --}}
                <section class="panel panel-pad" aria-labelledby="projects-title">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="panel-title" id="projects-title">Projects</h2>
                        <a class="text-sm" href="{{ route('projects.index') }}">All →</a>
                    </div>

                    @forelse ($projects as $project)
                        @php $pct = $project->tasks_count ? (int) round($project->done_tasks_count / $project->tasks_count * 100) : 0; @endphp
                        <a class="block mt-4 hover:no-underline" href="{{ route('projects.show', $project) }}">
                            <div class="flex justify-between text-sm">
                                <span class="font-semibold text-ink truncate">{{ $project->name }}</span>
                                <span class="text-muted">{{ $pct }}%</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-surface-3 overflow-hidden">
                                <div class="h-2 rounded-full bg-stage-done" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="text-xs text-muted mt-1">{{ $project->done_tasks_count }} of {{ $project->tasks_count }} done</p>
                        </a>
                    @empty
                        <p class="text-sm text-muted mt-3">No projects. <a href="{{ route('projects.create') }}">Create one</a>.</p>
                    @endforelse
                </section>

                {{-- Billing --}}
                <section class="panel panel-pad" aria-labelledby="billing-title">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="panel-title" id="billing-title">Billing this month</h2>
                        <span class="badge badge-muted">SIG</span>
                    </div>
                    <dl class="mt-3 text-sm">
                        <div class="flex justify-between py-2 border-b border-line">
                            <dt class="text-muted">Issued</dt>
                            <dd class="m-0 font-mono text-ok">{{ $money($billing['Issued']) }}</dd>
                        </div>
                        <div class="flex justify-between py-2">
                            <dt class="text-muted">In draft</dt>
                            <dd class="m-0 font-mono text-ink">{{ $money($billing['Draft']) }}</dd>
                        </div>
                    </dl>
                    <a class="text-sm" href="{{ route('billing.index') }}">Open billing →</a>
                </section>

                            </aside>
        </div>
    </div>
@endsection
