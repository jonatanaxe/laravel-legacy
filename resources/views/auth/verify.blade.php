@extends('layouts.app')

@section('title', 'Verify email')

@section('content')
    <section class="auth">
        <div class="auth-card">
            <p class="kicker">account</p>
            <h1>Verify your email</h1>
            <p class="sub">
                We sent a confirmation link to your address.
                If it didn't arrive, request another one.
            </p>

            @if (session('resent'))
                <div class="alert alert-ok" role="status">Novo link enviado.</div>
            @endif

            @if (Route::has('verification.resend'))
                <form method="POST" action="{{ route('verification.resend') }}" class="form">
                    @csrf
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block">Resend link</button>
                    </div>
                </form>
            @endif

            <p class="form-alt"><a href="{{ route('login') }}">Back to sign in</a></p>
        </div>
    </section>
@endsection
