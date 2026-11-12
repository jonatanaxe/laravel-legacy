@extends('layouts.app')

@section('title', 'Import #' . $import->id)

@section('content')
    <div class="wrap page page-narrow">
        <header class="page-head">
            <div>
                <p class="kicker">import report</p>
                <h1>{{ $import->original_name }}</h1>
                <p class="sub">
                    <span class="badge {{ $import->statusBadge() }}">{{ $import->statusLabel() }}</span>
                    · {{ $import->total_rows }} line(s) read
                    · {{ $import->imported_rows }} imported
                    · {{ $import->failed_rows }} rejected
                </p>
            </div>

            <div class="actions">
                <a class="btn btn-outline btn-sm" href="{{ route('tasks.index') }}">See tasks</a>
                <a class="btn btn-ghost btn-sm" href="{{ route('imports.index') }}">Back</a>
            </div>
        </header>

        @if ($import->status === 'failed')
            <div class="alert alert-error" role="alert">
                The import did not finish. No partial lines are guaranteed, so upload the file again.
            </div>
        @endif

        <div class="panel">
            <div class="panel-pad pb-3 border-b border-line">
                <h2 class="panel-title">Rejected lines</h2>
                <p class="text-sm text-muted mt-1">The number is the line in the file, counting the header. Valid lines are already tasks.</p>
            </div>
            @forelse ($import->errorsList() as $error)
                <article class="item">
                    <div class="item-main">
                        <h2 class="item-title">Line {{ $error['line'] ?? '?' }}</h2>
                        <p class="item-desc">{{ $error['message'] ?? '' }}</p>
                    </div>
                </article>
            @empty
                <div class="empty">
                    <h3>No rejected lines</h3>
                    <p>The whole file got in. Check the tasks in the list.</p>
                </div>
            @endforelse
        </div>

        @if ($import->failed_rows > count($import->errorsList()))
            <p class="sub">
                Showing the first {{ count($import->errorsList()) }} errors of {{ $import->failed_rows }}.
            </p>
        @endif
    </div>
@endsection
