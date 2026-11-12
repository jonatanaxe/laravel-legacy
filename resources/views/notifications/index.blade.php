@extends('layouts.app')

@section('title', 'Reminders')

@section('content')
    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">queue + scheduler</p>
                <h1>Reminders</h1>
                <p class="sub">
                    Created by the <code>tasks:due-soon</code> command (weekdays, 08:00)
                    or by "Remind me" on a task. They are stored, not lost in an email inbox.
                </p>
            </div>

            <div class="actions">
                @if ($unread > 0)
                    <form action="{{ route('notifications.readAll') }}" method="POST">
                        @csrf
                        <button class="btn btn-outline btn-sm" type="submit">Mark all as read</button>
                    </form>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        <div class="panel">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = $notification->read_at === null;
                @endphp

                <article class="item {{ $isUnread ? 'bg-brand-soft' : '' }}">
                    <div class="item-main">
                        <h2 class="item-title">
                            @if ($isUnread)
                                <span class="inline-block w-2 h-2 rounded-full bg-brand" aria-hidden="true"></span>
                            @endif
                            @if (!empty($data['task_id']))
                                <a class="text-ink hover:text-brand" href="{{ route('tasks.show', $data['task_id']) }}">{{ $data['title'] ?? 'Task' }}</a>
                            @else
                                {{ $data['title'] ?? 'Task' }}
                            @endif
                            <span class="badge {{ $isUnread ? 'badge-info' : 'badge-muted' }}">{{ $isUnread ? 'new' : 'read' }}</span>
                        </h2>

                        <p class="item-desc">{{ $data['message'] ?? '' }}</p>

                        <div class="item-tags">
                            @if (!empty($data['due_date']))
                                <span class="due">due {{ $data['due_date'] }}</span>
                            @endif
                            <span class="item-meta">
                                created {{ $notification->created_at ? $notification->created_at->format('M j, Y H:i') : '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="item-actions">
                        @if ($isUnread)
                            <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-ghost btn-sm" type="submit">Mark as read</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty">
                    <h3>No reminders</h3>
                    <p>When an open task gets close to its due date, it shows up here.</p>
                    <a class="btn btn-primary" href="{{ route('tasks.index') }}">See tasks</a>
                </div>
            @endforelse
        </div>

        {{ $notifications->links('partials.pagination') }}
    </div>
@endsection
