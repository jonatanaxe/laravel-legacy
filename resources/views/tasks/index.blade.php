@extends('layouts.app')

@section('title', 'My tasks')

@section('content')
    @php
        // Link builder that keeps the other filters.
        $link = function (array $overrides = []) use ($filters) {
            $params = array_merge($filters, $overrides);
            $params = array_filter($params, function ($v) {
                return $v !== '' && $v !== null;
            });
            return route('tasks.index', $params);
        };
        $total = (int) ($stats->total ?? 0);
        $open = (int) ($stats->open_count ?? 0);
        $done = (int) ($stats->done_count ?? 0);
        $overdue = (int) ($stats->overdue_count ?? 0);
    @endphp

    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">tasks</p>
                <h1>My tasks</h1>
                <p class="sub">
                    {{ $tasks->total() }} {{ $tasks->total() === 1 ? 'result' : 'results' }}
                    · signed in as {{ auth()->user()->email }}
                </p>
            </div>

            <div class="actions">
                <a class="btn btn-outline" href="{{ route('imports.index') }}">Import CSV</a>
                <a class="btn btn-ghost" href="{{ route('tasks.trash') }}">Trash</a>
                <a class="btn btn-primary" href="{{ route('tasks.create') }}">+ New task</a>
            </div>
        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        {{-- Aggregates in a single query (see TaskController::stats). --}}
        <section class="stats" aria-label="Summary">
            <div class="stat">
                <div class="stat-label">Total</div>
                <div class="stat-value">{{ $total }}</div>
                <div class="stat-note">tasks in the account</div>
            </div>
            <div class="stat">
                <div class="stat-label">Open</div>
                <div class="stat-value">{{ $open }}</div>
                <div class="stat-note">{{ $total > 0 ? round($open / $total * 100) : 0 }}% of total</div>
            </div>
            <div class="stat {{ $done > 0 ? 'is-ok' : '' }}">
                <div class="stat-label">Done</div>
                <div class="stat-value">{{ $done }}</div>
                <div class="stat-note">{{ $total > 0 ? round($done / $total * 100) : 0 }}% of total</div>
            </div>
            <div class="stat {{ $overdue > 0 ? 'is-danger' : '' }}">
                <div class="stat-label">Overdue</div>
                <div class="stat-value">{{ $overdue }}</div>
                <div class="stat-note">{{ $overdue > 0 ? 'open and past due' : 'nothing overdue' }}</div>
            </div>
        </section>

        <div class="toolbar">
            <nav class="chips" aria-label="Filter by status">
                <a class="chip {{ $filters['status'] === 'all' ? 'is-active' : '' }}" href="{{ $link(['status' => 'all']) }}"
                   @if ($filters['status'] === 'all') aria-current="page" @endif>
                    All <span class="n">{{ $total }}</span>
                </a>
                <a class="chip {{ $filters['status'] === 'open' ? 'is-active' : '' }}" href="{{ $link(['status' => 'open']) }}"
                   @if ($filters['status'] === 'open') aria-current="page" @endif>
                    Open <span class="n">{{ $open }}</span>
                </a>
                <a class="chip {{ $filters['status'] === 'done' ? 'is-active' : '' }}" href="{{ $link(['status' => 'done']) }}"
                   @if ($filters['status'] === 'done') aria-current="page" @endif>
                    Done <span class="n">{{ $done }}</span>
                </a>
                <a class="chip {{ $filters['status'] === 'overdue' ? 'is-active' : '' }}" href="{{ $link(['status' => 'overdue']) }}"
                   @if ($filters['status'] === 'overdue') aria-current="page" @endif>
                    Overdue <span class="n">{{ $overdue }}</span>
                </a>
            </nav>

            {{-- GET: filtering is a read and changes no state, so it needs no CSRF. --}}
            <form class="searchform" method="GET" action="{{ route('tasks.index') }}" role="search">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">

                <label class="sr-only" for="q">Search by title or description</label>
                <input id="q" class="input" type="search" name="q" maxlength="100"
                       value="{{ $filters['q'] }}" placeholder="Search title or description">

                <label class="sr-only" for="sort">Sort by</label>
                <select id="sort" class="select" name="sort">
                    <option value="priority" @if ($filters['sort'] === 'priority') selected @endif>Priority</option>
                    <option value="due" @if ($filters['sort'] === 'due') selected @endif>Due date</option>
                    <option value="recent" @if ($filters['sort'] === 'recent') selected @endif>Most recent</option>
                </select>

                <button class="btn btn-outline btn-sm" type="submit">Apply</button>

                @if ($filters['q'] !== '' || $filters['sort'] !== 'priority')
                    <a class="btn btn-ghost btn-sm" href="{{ $link(['q' => '', 'sort' => 'priority']) }}">Clear</a>
                @endif
            </form>
        </div>

        <div class="panel">
            @forelse ($tasks as $task)
                <article class="item">
                    <div class="item-main">
                        {{-- {{ }} escapes HTML: a <script> in the title shows up as text --}}
                        <h2 class="item-title">
                            <a class="text-ink hover:text-brand" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                            <span class="badge {{ $task->statusBadge() }}">{{ $task->statusLabel() }}</span>
                        </h2>

                        @if ($task->description)
                            <p class="item-desc">{{ $task->description }}</p>
                        @endif

                        <div class="item-tags">
                            <span class="badge {{ $task->priorityBadge() }}">{{ $task->priorityLabel() }} priority</span>

                            @if ($task->project)
                                <a class="badge badge-muted hover:no-underline" href="{{ route('projects.show', $task->project) }}">{{ $task->project->name }}</a>
                            @endif

                            @if ($task->due_date)
                                <span class="due {{ $task->isOverdue() ? 'is-late' : '' }}">
                                    due {{ $task->due_date->format('M j, Y') }}{{ $task->isOverdue() ? ' · overdue' : '' }}
                                </span>
                            @endif

                            <span class="item-meta">
                                #{{ $task->id }}
                                @if ($task->created_at)
                                    · created {{ $task->created_at->format('M j, Y') }}
                                @endif
                            </span>
                        </div>
                    </div>

                    {{-- Secondary actions in a menu: the list is for reading, not a row of
                         buttons per line. Alpine 2 (inline x-data). --}}
                    <div class="item-actions relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button type="button" class="btn btn-ghost btn-sm"
                                @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                                aria-label="Actions for {{ $task->title }}">
                            ⋯
                        </button>

                        <div x-show="open" x-cloak @click.away="open = false" role="menu"
                             class="absolute right-0 top-full z-20 mt-1 w-44 rounded-lg border border-line bg-surface shadow-lg py-1">
                            <a class="block px-4 py-2 text-sm text-ink hover:bg-surface-2 hover:no-underline"
                               href="{{ route('tasks.show', $task) }}" role="menuitem">Open</a>
                            <a class="block px-4 py-2 text-sm text-ink hover:bg-surface-2 hover:no-underline"
                               href="{{ route('tasks.edit', $task) }}" role="menuitem">Edit</a>

                            {{-- POST + @csrf + @method: never an <a href="/tasks/1/delete"> --}}
                            {{-- data-confirm: the confirm lives in public/js/lab.js (CSP without inline script) --}}
                            <form action="{{ route('tasks.destroy', $task) }}" method="POST"
                                  data-confirm="Move this task to trash?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" role="menuitem"
                                        class="block w-full text-left px-4 py-2 text-sm text-danger bg-surface hover:bg-danger-soft border-0 cursor-pointer">
                                    Move to trash
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty">
                    <h3>No tasks {{ $filters['status'] !== 'all' || $filters['q'] !== '' ? 'match this filter' : 'yet' }}</h3>
                    <p>
                        @if ($filters['q'] !== '')
                            Nothing found for "{{ $filters['q'] }}".
                        @else
                            Create the first one and watch CSRF and validation work underneath.
                        @endif
                    </p>
                    <a class="btn btn-primary" href="{{ route('tasks.create') }}">+ New task</a>
                </div>
            @endforelse
        </div>

        {{-- Pagination keeps status/q/sort through appends() in the controller. --}}
        {{ $tasks->links('partials.pagination') }}
    </div>
@endsection
