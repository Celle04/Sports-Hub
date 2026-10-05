@extends('layouts.auth')

@section('title', 'Reset Password | SNNHS Sports Activity Hub')

@section('content')
    <div class="auth-heading">
        <h2>Reset your password</h2>
        <p>Choose a new password for your Sports Activity Hub account.</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('password.update') }}" aria-label="Set a new password" data-loading-label="Updating...">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="auth-field">
            <label for="email">Email address</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-mail"></use></svg>
                <input id="email" name="email" type="email" placeholder="Enter your email" autocomplete="email" value="{{ old('email', $email) }}" required readonly>
            </span>
            @error('email')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password">New password</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-lock"></use></svg>
                <input id="password" class="auth-input-toggle" name="password" type="password" placeholder="Enter new password" autocomplete="new-password" required autofocus>
                <button class="auth-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                </button>
            </span>
            @error('password')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation">Confirm new password</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-lock"></use></svg>
                <input id="password_confirmation" class="auth-input-toggle" name="password_confirmation" type="password" placeholder="Confirm new password" autocomplete="new-password" required>
                <button class="auth-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                    <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                </button>
            </span>
        </div>

        <button class="button auth-submit" type="submit">Reset Password</button>

        <a class="auth-back" href="{{ route('login') }}"><svg aria-hidden="true"><use href="#icon-arrow-left"></use></svg> Back to Login</a>
    </form>
@endsection