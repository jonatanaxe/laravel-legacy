@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="wrap page page-narrow">
        <header class="page-head">
            <div>
                <p class="kicker">home</p>
                <h1>All set, {{ auth()->user()->name }}</h1>
                <p class="sub">Your session is active.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        <div class="panel panel-pad">
            <p>Tasks live at <a href="{{ route('tasks.index') }}">/tasks</a>.</p>
        </div>
    </div>
@endsection
