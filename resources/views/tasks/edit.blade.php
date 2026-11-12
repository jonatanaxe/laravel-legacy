@extends('layouts.app')

@section('title', 'Edit task #' . $task->id)

@section('content')
    @include('tasks._form', ['task' => $task])
@endsection
