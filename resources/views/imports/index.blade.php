@extends('layouts.app')

@section('title', 'Import tasks')

@section('content')
    <div class="wrap page page-narrow">
        <header class="page-head">
            <div>
                <p class="kicker">bulk entry</p>
                <h1>Import tasks</h1>
                <p class="sub">
                    The CSV is received immediately, processed on a queue and checked line by line.
                    You are the owner; the file doesn't choose one.
                </p>
            </div>
        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Nothing was imported:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="panel panel-pad">
            <form method="POST" action="{{ route('imports.store') }}"
                  enctype="multipart/form-data" class="form">
                @csrf

                <div class="field">
                    <label class="label" for="file">CSV file</label>
                    <input id="file" class="input" type="file" name="file" required accept=".csv,text/csv">
                    <p class="hint">
                        Up to 10 MB and 5000 lines. Columns: <code>title, description, priority,
                        due_date, done</code>, or no header in that order.
                        <code>user_id</code> and <code>project_id</code> columns in the file are ignored.
                    </p>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Import</button>
                    <a class="btn btn-ghost" href="{{ asset('samples/tasks-template.csv') }}" download>Download CSV template</a>
                </div>
            </form>
        </div>

        <div class="panel mt-6">
            <div class="panel-pad pb-3 border-b border-line">
                <h2 class="panel-title">Recent imports</h2>
                <p class="text-sm text-muted mt-1">The report keeps how many lines got in and why the others were rejected.</p>
            </div>
            @forelse ($imports as $import)
                <article class="item">
                    <div class="item-main">
                        <h2 class="item-title">
                            <a href="{{ route('imports.show', $import->id) }}">{{ $import->original_name }}</a>
                            <span class="badge {{ $import->statusBadge() }}">{{ $import->statusLabel() }}</span>
                        </h2>

                        <div class="item-tags">
                            <span class="item-meta">{{ $import->imported_rows }} imported</span>
                            <span class="item-meta">{{ $import->failed_rows }} with errors</span>
                            <span class="item-meta">
                                {{ $import->created_at ? $import->created_at->format('M j, Y H:i') : '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="item-actions">
                        <a class="btn btn-outline btn-sm" href="{{ route('imports.show', $import->id) }}">Report</a>
                        <form action="{{ route('imports.destroy', $import->id) }}" method="POST"
                              data-confirm="Remove this report? The imported tasks stay.">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-danger" type="submit">Remove</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty">
                    <h3>No imports yet</h3>
                    <p>Upload a CSV above: valid lines become your tasks, invalid ones show up in the report.</p>
                </div>
            @endforelse
        </div>

        {{ $imports->links('partials.pagination') }}
    </div>
@endsection
