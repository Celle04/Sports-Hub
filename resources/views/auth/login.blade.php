@extends('layouts.auth')

@section('title', 'Login | SNNHS SportsHub')

@section('content')
    <div class="auth-heading">
        <h2>Welcome!</h2>
        <p>Sign in to access your SportsHub dashboard.</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('login.submit') }}" aria-label="Sports Activity Hub sign in form" data-loading-label="Logging in...">
        @csrf

        <div class="auth-field">
            <label for="role">Login as</label>
            <select id="role" name="role" required>
                <option value="Administrator" @selected(old('role', 'Administrator') === 'Administrator')>Administrator / Sports Coordinator</option>
                <option value="Student" @selected(old('role') === 'Student')>Athlete / Student</option>
            </select>
            @error('role')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="username">Email or school email</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-mail"></use></svg>
                <input id="username" name="username" type="text" placeholder="Enter your email" autocomplete="username" value="{{ old('username') }}" required autofocus>
            </span>
            @error('username')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password">Password</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-lock"></use></svg>
                <input id="password" class="auth-input-toggle" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                <button class="auth-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                </button>
            </span>
            @error('password')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-row">
            <label class="auth-check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Remember me</span>
            </label>
            <a class="auth-link" href="{{ route('password.request') }}">Forgot Password?</a>
        </div>

        <button class="button auth-submit" type="submit">Login</button>

        <a class="auth-back" href="{{ url('/') }}">Back to Home</a>
    </form>
@endsection