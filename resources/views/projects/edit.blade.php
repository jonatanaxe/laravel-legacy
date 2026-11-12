@extends('layouts.app')

@section('title', 'Edit ' . $project->name)

@section('content')
    @include('projects._form')
@endsection
