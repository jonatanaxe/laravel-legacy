@extends('layouts.app')

@section('title', 'Projects')

@section('content')
    @php
        $roleLabel = ['owner' => 'owner', 'member' => 'member', 'viewer' => 'viewer'];
        $roleBadge = ['owner' => 'badge-info', 'member' => 'badge-ok', 'viewer' => 'badge-muted'];
    @endphp

    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">teams</p>
                <h1>Projects</h1>
                <p class="sub">
                    Tasks inside a project are shared. Tasks without a project stay
                    personal: only the owner sees them.
                </p>
            </div>

            <a class="btn btn-primary" href="{{ route('projects.create') }}">+ New project</a>
        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projects as $project)
                @php
                    // The role comes ready from the controller (one query), not
                    // one query per card.
                    $role = $project->user_id === auth()->id()
                        ? 'owner'
                        : ($roles[$project->id] ?? 'viewer');
                    $percent = $project->tasks_count ? (int) round($project->done_tasks_count / $project->tasks_count * 100) : 0;
                @endphp

                <article class="panel panel-pad flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-lg">
                            <a class="text-ink hover:text-brand" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                        </h2>
                        <span class="badge {{ $roleBadge[$role] ?? 'badge-muted' }}">{{ $roleLabel[$role] ?? $role }}</span>
                    </div>

                    <p class="text-sm text-body mt-2 flex-1">{{ $project->description ?: 'No description.' }}</p>

                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-muted mb-1">
                            <span>{{ $project->done_tasks_count }} of {{ $project->tasks_count }} done</span>
                            <span class="font-semibold text-ink">{{ $percent }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-3 overflow-hidden" role="progressbar"
                             aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress">
                            <div class="h-2 rounded-full bg-stage-done" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 mt-4 pt-4 border-t border-line text-sm">
                        <span class="text-muted">{{ $project->users_count }} {{ $project->users_count == 1 ? 'person' : 'people' }}</span>
                        <span class="flex gap-3">
                            <a href="{{ route('projects.timeline', $project) }}">Schedule</a>
                            <a class="font-semibold" href="{{ route('projects.show', $project) }}">Open →</a>
                        </span>
                    </div>
                </article>
            @empty
                <div class="panel panel-pad md:col-span-2 xl:col-span-3">
                    <div class="empty">
                        <h3>No projects yet</h3>
                        <p>Create one and invite someone by email: you choose whether they edit or only read.</p>
                        <a class="btn btn-primary" href="{{ route('projects.create') }}">+ New project</a>
                    </div>
                </div>
            @endforelse
        </div>

        {{ $projects->links('partials.pagination') }}
    </div>
@endsection
