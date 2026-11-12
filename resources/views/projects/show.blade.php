@extends('layouts.app')

@section('title', $project->name)

{{--
    This page is not a task lister: it is the reading of the project.
    The timeline fills the main column - the story of how the work moved,
    week by week. The right column answers "what now?": what is open and who
    is holding what. The full list stays at /tasks.
--}}
@section('content')
    @php
        $roleLabel = ['owner' => 'owner', 'member' => 'member', 'viewer' => 'viewer'];
        $roleBadge = ['owner' => 'badge-info', 'member' => 'badge-ok', 'viewer' => 'badge-muted'];
        $stages = \App\Task::statusLabels();
        $stageCounts = \App\Task::statusLabelsPlural();
        $actions = \App\TaskEvent::labels();
        $progress = $flow['progress'];
        $history = $flow['history'];

        // null never becomes "0 days": an empty sample is not zero time.
        $d = function ($value) {
            return $value === null ? '-' : $value;
        };
    @endphp

    <div class="wrap page">
        @include('projects._header', ['tab' => 'overview'])

        {{-- Where we are, in one line: number, bar and composition by stage. --}}
        <section class="panel panel-pad mt-6" aria-labelledby="progress-title">
            <div class="progress-head">
                <div class="progress-summary">
                    <p class="kicker">progress</p>
                    <div class="progress-value" id="progress-title">
                        {{ $snapshot['percent'] }}%
                    </div>
                    <p class="sub">
                        {{ $snapshot['percent'] }}% done ·
                        {{ $snapshot['done'] }} of {{ $snapshot['total'] }} tasks ·
                        {{ $snapshot['open'] }} open · {{ $snapshot['wip'] }} in progress
                    </p>
                    @if ($snapshot['overdue'])
                        <span class="badge badge-danger mt-2">{{ $snapshot['overdue'] }} overdue</span>
                    @endif
                </div>

                <ul class="legend">
                    @foreach ($progress['segments'] as $segment)
                        <li class="legend-item">
                            <span class="swatch swatch-{{ $segment['key'] }}" aria-hidden="true"></span>
                            {{ $segment['label'] }}
                            <b>{{ $segment['value'] }}</b>
                        </li>
                    @endforeach
                </ul>
            </div>

            <figure class="chart">
                <svg viewBox="0 0 {{ $progress['width'] }} {{ $progress['height'] }}"
                     role="img" aria-labelledby="progress-desc">
                    <desc id="progress-desc">
                        @foreach ($progress['segments'] as $segment)
                            {{ $segment['label'] }} {{ $segment['value'] }}@if (!$loop->last), @endif
                        @endforeach
                    </desc>

                    @foreach ($progress['segments'] as $segment)
                        @if ($segment['w'] > 0)
                            <rect class="band-{{ $segment['key'] }}"
                                  x="{{ $segment['x'] }}" y="0"
                                  width="{{ $segment['w'] }}" height="{{ $progress['height'] }}"
                                  rx="3" />
                        @endif
                    @endforeach
                </svg>
            </figure>
        </section>

        <section class="stats" aria-label="Project summary">
            <div class="stat">
                <div class="stat-label">In progress</div>
                <div class="stat-value">{{ $snapshot['wip'] }}</div>
                <div class="stat-note">doing + review</div>
            </div>
            <div class="stat">
                <div class="stat-label">Open</div>
                <div class="stat-value">{{ $snapshot['open'] }}</div>
                <div class="stat-note">of {{ $snapshot['total'] }} in the project</div>
            </div>
            <div class="stat {{ $snapshot['overdue'] > 0 ? 'is-danger' : '' }}">
                <div class="stat-label">Overdue</div>
                <div class="stat-value">{{ $snapshot['overdue'] }}</div>
                <div class="stat-note">{{ $snapshot['overdue'] > 0 ? 'past due date' : 'nothing past due' }}</div>
            </div>
            <div class="stat is-ok">
                <div class="stat-label">Delivered in 7 days</div>
                <div class="stat-value">{{ $flow['summary']['last7'] }}</div>
                <div class="stat-note">{{ $flow['summary']['per_week'] }} per week (4 weeks)</div>
            </div>
        </section>

        <div class="split">
            {{-- Timeline: the main piece of the page. Most recent first - the top
                 answers "where are we now", the rest explains how we got here. --}}
            <section class="panel panel-pad" aria-labelledby="timeline-title">
                <div class="chart-head">
                    <div>
                        <h2 class="panel-title" id="timeline-title">Timeline</h2>
                        <p class="hint">
                            {{ $history['weeks_total'] }} weeks · {{ $history['from'] }} to {{ $history['to'] }}.
                            Each bar is the state of the work at the end of the week; the lines are what moved.
                        </p>
                    </div>

                    <p class="hint timeline-now">
                        now: <strong>{{ $snapshot['done'] }}/{{ $snapshot['total'] }}</strong>
                    </p>
                </div>

                <div class="timeline-progress">
                    <div class="timeline-progress-bar" style="width: {{ $snapshot['percent'] }}%"></div>
                </div>

                <div class="timeline">
                    @forelse (array_reverse($history['weeks']) as $week)
                        <div class="tl-item {{ $week['current'] ? 'is-current' : '' }}">
                            <div class="tl-when">
                                {{ $week['range'] }}
                                @if ($week['current'])
                                    <span class="badge badge-info">now</span>
                                @endif
                                @if ($week['moved'] > 0)
                                    <span class="badge badge-secondary mt-1">+{{ $week['moved'] }} moves</span>
                                @endif
                            </div>

                            <div class="tl-what">
                                <div class="week-head">
                                    <span class="week-count">
                                        {{ $week['done'] }} of {{ $week['total'] }} done
                                    </span>
                                    <span class="week-meta">
                                        @if ($week['completed'] > 0)
                                            <b class="week-delta">+{{ $week['completed'] }}</b> this week
                                        @else
                                            0 this week
                                        @endif
                                        · {{ $week['entered'] }} added
                                    </span>
                                </div>

                                <figure class="chart week-bar">
                                    <svg viewBox="0 0 {{ $week['chart']['width'] }} {{ $week['chart']['height'] }}"
                                         role="img" aria-label="Project state at the end of week {{ $week['range'] }}">
                                        @foreach ($week['chart']['segments'] as $segment)
                                            @if ($segment['w'] > 0)
                                                <rect class="band-{{ $segment['key'] }}"
                                                      x="{{ $segment['x'] }}" y="0"
                                                      width="{{ $segment['w'] }}" height="{{ $week['chart']['height'] }}"
                                                      rx="2" />
                                            @endif
                                        @endforeach
                                    </svg>
                                </figure>

                                <ul class="week-stages">
                                    @foreach ($stageCounts as $key => $label)
                                        <li>
                                            <span class="swatch swatch-{{ $key }}" aria-hidden="true"></span>
                                            {{ $week['by_stage'][$key] }} {{ $label }}
                                        </li>
                                    @endforeach
                                </ul>

                                @if (count($week['highlight']))
                                    <ul class="week-events">
                                        @foreach ($week['highlight'] as $event)
                                            <li>
                                                <strong>{{ $names->get($event['user_id'], 'system') }}</strong>
                                                {{ $actions[$event['action']] ?? $event['action'] }}
                                                <a href="{{ route('tasks.show', $event['task_id']) }}">{{ $event['title'] }}</a>
                                                @if ($event['action'] === 'status_changed')
                                                    → {{ $stages[$event['status']] ?? $event['status'] }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="sub">No tasks in the project yet. The timeline starts with the first one.</p>
                    @endforelse
                </div>
            </section>

            <aside class="split-aside">
                {{-- "What now?": what is open and who is holding what. --}}
                <section class="panel panel-pad" aria-labelledby="now-title">
                    <div class="section-head mb-3">
                        <h2 class="panel-title" id="now-title">Open now</h2>
                        <p>{{ $snapshot['open'] }} open · {{ $snapshot['wip'] }} in progress</p>
                    </div>

                    @if (count($openTasks))
                        <ul class="now-list">
                            @foreach ($openTasks as $task)
                                <li>
                                    <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                    <span class="badge {{ $task->statusBadge() }}">{{ $task->statusLabel() }}</span>
                                    <span class="now-meta">
                                        {{ $task->user->name ?? '-' }}
                                        @if ($task->due_date)
                                            · <span class="{{ $task->isOverdue() ? 'is-late' : '' }}">
                                                due {{ $task->due_date->format('M j') }}{{ $task->isOverdue() ? ' (overdue)' : '' }}
                                            </span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($snapshot['open'] > count($openTasks))
                            <p class="sub mt-3">
                                + {{ $snapshot['open'] - count($openTasks) }} more open
                                <a href="{{ route('tasks.index') }}">in the full list</a>.
                            </p>
                        @endif
                    @else
                        <p class="sub">Nothing open: the project is up to date.</p>
                    @endif
                </section>

                {{-- Team: workload and members in one list. It used to be two panels
                     and the same person showed up twice. --}}
                <section class="panel panel-pad" aria-labelledby="team-title">
                    <div class="section-head mb-3">
                        <h2 class="panel-title" id="team-title">Team</h2>
                        <p>Who is holding what. The role applies to the whole project.</p>
                    </div>

                    @forelse ($members as $member)
                        <div class="member">
                            <div class="member-head">
                                <span class="member-name">
                                    {{ $member['name'] }}
                                    <span class="badge {{ $roleBadge[$member['role']] ?? 'badge-muted' }}">
                                        {{ $roleLabel[$member['role']] ?? ($member['role'] ?: 'outside') }}
                                    </span>
                                </span>
                                <span class="member-count">
                                    <b>{{ $member['open'] }}</b> open · {{ $member['done'] }} done
                                </span>
                            </div>

                            @if ($member['total'] > 0)
                                <figure class="chart member-bar">
                                    <svg viewBox="0 0 {{ $member['chart']['width'] }} {{ $member['chart']['height'] }}"
                                         role="img" aria-label="{{ $member['name'] }}: {{ $member['open'] }} open, {{ $member['done'] }} done">
                                        @foreach ($member['chart']['segments'] as $segment)
                                            @if ($segment['w'] > 0)
                                                <rect class="band-{{ $segment['key'] }}"
                                                      x="{{ $segment['x'] }}" y="0"
                                                      width="{{ $segment['w'] }}" height="{{ $member['chart']['height'] }}"
                                                      rx="2" />
                                            @endif
                                        @endforeach
                                    </svg>
                                </figure>
                            @endif

                            <div class="member-foot">
                                <span>{{ $member['email'] ?? '' }}</span>

                                @can('manageMembers', $project)
                                    @if ($member['role'] !== 'owner')
                                        <form action="{{ route('projects.members.destroy', [$project, $member['id']]) }}"
                                              method="POST" data-confirm="Remove {{ $member['name'] }} from the project?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="sub">Nobody in the project yet.</p>
                    @endforelse

                    @can('manageMembers', $project)
                        <form method="POST" action="{{ route('projects.members.store', $project) }}" class="searchform mt-4">
                            @csrf
                            <label class="sr-only" for="email">Person's email</label>
                            <input id="email" class="input" type="email" name="email" required autocomplete="off"
                                   placeholder="person@example.com" value="{{ old('email') }}">

                            <label class="sr-only" for="role">Role</label>
                            <select id="role" class="select" name="role">
                                <option value="member">member (edits)</option>
                                <option value="viewer">viewer (read only)</option>
                            </select>

                            <button class="btn btn-primary btn-sm" type="submit">Add</button>
                        </form>
                    @endcan
                </section>
            </aside>
        </div>

        <p class="sub mt-4">
            The numbers on this page come from the audit trail, not from typed counts.
            <a href="{{ route('flow.index') }}">See the full flow</a> ·
            <a href="{{ route('tasks.index') }}">all tasks</a>.
        </p>
    </div>
@endsection
