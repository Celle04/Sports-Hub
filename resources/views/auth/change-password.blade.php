@extends('layouts.auth')

@section('title', 'Change Password | SNNHS SportsHub')

@section('content')
    <div class="auth-heading">
        <h2>Change your password</h2>
        <p>A temporary password was set for your account. Choose a new password to continue.</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('student.change-password.update') }}" aria-label="Change password form" data-loading-label="Saving...">
        @csrf

        <div class="auth-field">
            <label for="current_password">Current (temporary) password</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-lock"></use></svg>
                <input id="current_password" class="auth-input-toggle" name="current_password" type="password" placeholder="Enter your temporary password" autocomplete="current-password" required autofocus>
                <button class="auth-toggle" type="button" data-password-toggle="current_password" aria-label="Show password" aria-pressed="false">
                    <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                </button>
            </span>
            @error('current_password')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password">New password</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-lock"></use></svg>
                <input id="password" class="auth-input-toggle" name="password" type="password" placeholder="Enter new password" autocomplete="new-password" required>
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
            @error('password_confirmation')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <p class="otp-note">Your new password must be at least 8 characters and different from your temporary password.</p>

        <button class="button auth-submit" type="submit">Save New Password</button>
        <a class="auth-back" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out instead</a>
        <form id="logout-form" style="display:none" method="POST" action="{{ route('logout') }}">@csrf</form>
    </form>
@endsection
