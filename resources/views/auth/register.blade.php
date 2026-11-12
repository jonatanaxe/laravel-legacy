@extends('layouts.app')

@section('title', 'Create account')

@section('content')
    <section class="auth">
        <div class="auth-card">
            <p class="kicker">sign up</p>
            <h1>Create account</h1>
            <p class="sub">The password is stored with <code>bcrypt</code>; the hash never leaves in JSON.</p>

            <form method="POST" action="{{ route('register') }}" class="form">
                @csrf

                <div class="field">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" type="text"
                           class="input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                           value="{{ old('name') }}" required autocomplete="name" autofocus
                           @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                    @error('name')
                        <p class="field-error" id="name-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email"
                           class="input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                           value="{{ old('email') }}" required autocomplete="email"
                           @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                    @error('email')
                        <p class="field-error" id="email-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password"
                           class="input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                           required autocomplete="new-password"
                           @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                    @error('password')
                        <p class="field-error" id="password-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password-confirm">Confirm password</label>
                    <input id="password-confirm" name="password_confirmation" type="password"
                           class="input" required autocomplete="new-password">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">Create account</button>
                </div>

                <p class="form-alt">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                </p>
            </form>
        </div>
    </section>
@endsection
