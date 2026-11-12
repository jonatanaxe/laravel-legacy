@extends('layouts.app')

@section('title', '409 · edit conflict')

@section('content')
    <div class="wrap page page-narrow">
        <header class="page-head">
            <div>
                <p class="kicker">conflict</p>
                <h1>This task changed while you were editing</h1>
                <p class="sub">
                    Another tab (or another person) saved first. To avoid wiping out their
                    work, this change was rejected.
                </p>
            </div>
        </header>

        <div class="panel panel-pad">
            <p class="sub mt-0">
                Reload the page to see the current version and redo your change.
                That is optimistic locking working as intended: the last one to save
                doesn't silently win.
            </p>

            <div class="form-actions">
                <a class="btn btn-primary" href="{{ route('tasks.index') }}">See my tasks</a>
            </div>
        </div>
    </div>
@endsection
