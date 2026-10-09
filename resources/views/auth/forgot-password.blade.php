@extends('layouts.auth')

@section('title', 'Forgot Password | SNNHS SportsHub')

@section('content')
    <div class="auth-heading">
        <h2>Forgot your password?</h2>
        <p>Enter your email address and we'll send you a 6-digit verification code to reset your password.</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('password.email') }}" aria-label="Request a password reset code" data-loading-label="Sending code...">
        @csrf

        <div class="auth-field">
            <label for="email">Email address</label>
            <span class="auth-control">
                <svg class="auth-control-icon" aria-hidden="true"><use href="#icon-mail"></use></svg>
                <input id="email" name="email" type="email" placeholder="Enter your email" autocomplete="email" value="{{ old('email') }}" required autofocus>
            </span>
            @error('email')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <button class="button auth-submit" type="submit">Send OTP</button>

        <a class="auth-back" href="{{ route('login') }}"><svg aria-hidden="true"><use href="#icon-arrow-left"></use></svg> Back to Login</a>
    </form>
@endsection