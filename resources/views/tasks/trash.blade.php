@extends('layouts.app')

@section('title', 'Trash')

@section('content')
    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">soft delete</p>
                <h1>Trash</h1>
                <p class="sub">
                    {{ $tasks->total() }} deleted {{ $tasks->total() === 1 ? 'task' : 'tasks' }}
                    · only ones you can see, and nothing is gone for good without a second confirmation
                </p>
            </div>

        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        <div class="panel">
            @forelse ($tasks as $task)
                <article class="item">
                    <div class="item-main">
                        <h2 class="item-title">
                            {{ $task->title }}
                            <span class="badge badge-warn">apagada</span>
                        </h2>

                        @if ($task->description)
                            <p class="item-desc">{{ $task->description }}</p>
                        @endif

                        <div class="item-tags">
                            <span class="item-meta">#{{ $task->id }}</span>
                            @if ($task->project)
                                <span class="badge badge-muted">{{ $task->project->name }}</span>
                            @endif
                            <span class="due">
                                deleted {{ $task->deleted_at ? $task->deleted_at->format('M j, Y H:i') : '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="item-actions">
                        <form action="{{ route('tasks.restore', $task->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-primary btn-sm" type="submit">Restore</button>
                        </form>

                        {{-- Permanent delete is irreversible: confirm in lab.js (CSP without inline). --}}
                        <form action="{{ route('tasks.forceDelete', $task->id) }}" method="POST"
                              data-confirm="Delete permanently? The audit trail stays.">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-danger" type="submit">Delete permanently</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty">
                    <h3>Trash is empty</h3>
                    <p>Whatever you delete from the list lands here, with its audit trail intact.</p>
                    <a class="btn btn-primary" href="{{ route('tasks.index') }}">Back to tasks</a>
                </div>
            @endforelse
        </div>

        {{ $tasks->links('partials.pagination') }}
    </div>
@endsection
