@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <section class="auth">
        <div class="auth-card">
            <p class="kicker">Atlas ERP · PALO IT technical case</p>
            <h1>Sign in</h1>
            <p class="sub">
                Test accounts: <code>ana@example.com</code> and <code>bruno@example.com</code>,
                password <code>secret123</code>.
            </p>

            <form method="POST" action="{{ route('login') }}" class="form">
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

                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password"
                           class="input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                           required autocomplete="current-password"
                           @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                    @error('password')
                        <p class="field-error" id="password-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="check" for="remember">
                        <input id="remember" name="remember" type="checkbox" {{ old('remember') ? 'checked' : '' }}>
                        <span>Remember me</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
                </div>

                <p class="form-alt">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot password</a>
                    @endif
                    @if (Route::has('register'))
                        · <a href="{{ route('register') }}">Create account</a>
                    @endif
                </p>
            </form>
        </div>
    </section>
@endsection
