@extends('layouts.app')

@section('title', 'Set a new password')

@section('content')
    <section class="auth">
        <div class="auth-card">
            <p class="kicker">reset</p>
            <h1>Set a new password</h1>
            <p class="sub">Choose a new password for the account.</p>

            <form method="POST" action="{{ route('password.update') }}" class="form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email"
                           class="input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                           value="{{ old('email', $email ?? '') }}" required autocomplete="email" autofocus
                           @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                    @error('email')
                        <p class="field-error" id="email-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password">New password</label>
                    <input id="password" name="password" type="password"
                           class="input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                           required autocomplete="new-password"
                           @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                    @error('password')
                        <p class="field-error" id="password-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password-confirm">Confirm new password</label>
                    <input id="password-confirm" name="password_confirmation" type="password"
                           class="input" required autocomplete="new-password">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">Save new password</button>
                </div>
            </form>
        </div>
    </section>
@endsection
