{{--
    Shared header of the project screens: title, actions and tabs.
    Receives: $project, $role, $tab ('overview' | 'schedule').

    Tabs are real links (each has its own URL, opens in a new tab, works
    without JS). Alpine is only used for the secondary actions menu.
--}}
@php
    $roleLabel = ['owner' => 'owner', 'member' => 'member', 'viewer' => 'viewer'];
    $tabs = [
        'overview' => ['label' => 'Overview', 'url' => route('projects.show', $project)],
        'schedule' => ['label' => 'Schedule', 'url' => route('projects.timeline', $project)],
    ];
@endphp

<nav class="text-sm text-muted mb-3" aria-label="You are here">
    <a class="text-muted hover:text-ink" href="{{ route('projects.index') }}">Projects</a>
    <span class="mx-1" aria-hidden="true">/</span>
    <span class="text-ink">{{ $project->name }}</span>
</nav>

<header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-3xl">{{ $project->name }}</h1>
        <p class="mt-1 text-body">
            {{ $project->description ?: 'No description.' }}
            <span class="badge badge-muted ml-1">you are {{ $roleLabel[$role] ?? $role }}</span>
        </p>
    </div>

    <div class="flex items-center gap-2">
        @can('createTask', $project)
            <a class="btn btn-primary btn-sm" href="{{ route('tasks.create', ['project' => $project->id]) }}">
                + New task
            </a>
        @endcan

        @if (auth()->user()->can('update', $project) || auth()->user()->can('delete', $project))
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" class="btn btn-outline btn-sm"
                        @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                        aria-label="More project actions">
                    ⋯
                </button>

                <div x-show="open" x-cloak @click.away="open = false"
                     class="absolute right-0 z-20 mt-2 w-56 rounded-lg border border-line bg-surface shadow-lg py-1"
                     role="menu">
                    @can('update', $project)
                        <a class="block px-4 py-2 text-sm text-ink hover:bg-surface-2 hover:no-underline"
                           href="{{ route('projects.edit', $project) }}" role="menuitem">Edit project</a>
                    @endcan

                    @can('delete', $project)
                        <form action="{{ route('projects.destroy', $project) }}" method="POST"
                              data-confirm="Delete the project? Its tasks become personal and are not deleted.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" role="menuitem"
                                    class="block w-full text-left px-4 py-2 text-sm text-danger bg-surface hover:bg-danger-soft border-0 cursor-pointer">
                                Delete project
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        @endif
    </div>
</header>

<nav class="mt-6 flex gap-1 border-b border-line" aria-label="Project sections">
    @foreach ($tabs as $key => $item)
        <a href="{{ $item['url'] }}"
           class="-mb-px px-4 py-2 text-sm font-semibold border-b-2 hover:no-underline
                  {{ $tab === $key ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink' }}"
           @if ($tab === $key) aria-current="page" @endif>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>

@if (session('status'))
    <div class="alert alert-ok mt-4" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error mt-4" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
