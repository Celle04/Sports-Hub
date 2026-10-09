@extends('layouts.auth')

@section('title', 'Verify Your Email | SNNHS SportsHub')

@section('content')
    <div class="auth-heading">
        <h2>Verify your email</h2>
        <p>We sent a 6-digit verification code to</p>
        <p class="otp-sent-to">{{ $maskedEmail }}</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('password.otp.check') }}" aria-label="Verify your verification code" data-loading-label="Verifying...">
        @csrf

        <div class="otp-field" data-otp-field>
            <label for="otp-box-0">Enter your 6-digit code</label>
            <div class="otp-boxes" data-otp-boxes>
                @for ($i = 0; $i < 6; $i++)
                    <input class="otp-box" id="otp-box-{{ $i }}" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="Digit {{ $i + 1 }}" pattern="[0-9]">
                @endfor
            </div>
            <input type="hidden" name="otp" data-otp-value>
            @error('otp')<p class="auth-error">{{ $message }}</p>@enderror
        </div>

        <button class="button auth-submit" type="submit">Verify OTP</button>
    </form>

    <div class="otp-resend">
        <p class="otp-resend-help">Didn't receive the code?</p>
        <form method="POST" action="{{ route('password.otp.resend') }}" aria-label="Resend the verification code">
            @csrf
            <button class="otp-resend-btn" type="submit" data-resend-button data-lock-until="{{ $resendLock }}">Resend OTP</button>
        </form>
        <p class="otp-countdown" data-resend-countdown aria-live="polite"></p>
    </div>

    <p class="otp-note">For your security, this code expires in 5 minutes and can only be used once.</p>

    <a class="auth-back" href="{{ route('login') }}"><svg aria-hidden="true"><use href="#icon-arrow-left"></use></svg> Back to Login</a>
@endsection