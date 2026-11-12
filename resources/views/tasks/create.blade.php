@extends('layouts.app')

@section('title', 'New task')

@section('content')
    @include('tasks._form', ['task' => $task])
@endsection
