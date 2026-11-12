@extends('layouts.app')

@section('title', 'Recover password')

@section('content')
    <section class="auth">
        <div class="auth-card">
            <p class="kicker">recovery</p>
            <h1>Recover password</h1>
            <p class="sub">Enter the account email and we will send you a link to reset the password.</p>

            @if (session('status'))
                <div class="alert alert-ok" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="form">
                @csrf

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email"
                           class="input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                           value="{{ old('email') }}" required autocomplete="email" autofocus
                           @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                    @error('email')
                        <p class="field-error" id="email-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">Send link</button>
                </div>

                <p class="form-alt"><a href="{{ route('login') }}">Back to sign in</a></p>
            </form>
        </div>
    </section>
@endsection
