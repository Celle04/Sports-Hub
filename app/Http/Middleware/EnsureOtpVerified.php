<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpVerified
{
    /**
     * Must match PasswordResetController::VERIFY_SESSION_TTL_MINUTES.
     */
    protected const VERIFY_SESSION_TTL_MINUTES = 10;

    /**
     * The reset form may only be reached after the user has verified the OTP
     * in this same browser. A forged/absent session flag or one that is too
     * old sends the visitor back to the start of the flow.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->session()->get('password_reset_email');
        $verifiedAt = $request->session()->get('password_reset_verified_at');

        if (! $email || ! $verifiedAt) {
            return redirect()->route('password.request');
        }

        if (now()->getTimestamp() - Carbon::parse($verifiedAt)->getTimestamp() > self::VERIFY_SESSION_TTL_MINUTES * 60) {
            $request->session()->forget(['password_reset_email', 'password_reset_verified_at']);

            return redirect()->route('password.request');
        }

        return $next($request);
    }
}