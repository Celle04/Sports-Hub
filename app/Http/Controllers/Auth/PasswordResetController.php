<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtp;
use App\Models\PasswordResetOtp as PasswordResetOtpModel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    /**
     * How long an issued OTP stays valid.
     */
    protected const OTP_TTL_MINUTES = 5;

    /**
     * Number of wrong attempts allowed before the OTP is invalidated.
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * Minimum time between OTP emails for the same address.
     */
    protected const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * How long a successfully verified session may reach the reset form.
     */
    protected const VERIFY_SESSION_TTL_MINUTES = 10;

    protected const SESSION_EMAIL = 'password_reset_email';
    protected const SESSION_VERIFIED_AT = 'password_reset_verified_at';

    /**
     * GET /forgot-password
     */
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * POST /forgot-password
     *
     * Issues a 6-digit OTP, persists only its hash, emails the plain code
     * straight to the account's real address and keeps the reply from ever
     * hinting whether the address belongs to an account.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()
                ->with('success', 'If an account exists with that email, a password reset link has been sent.')
                ->withInput($request->only('email'));
        }

        $result = $this->issueOtp($user);

        try {
            Mail::to($user->email)->send(new PasswordResetOtp($result['otp']));
        } catch (Throwable $exception) {
            $result['record']->delete();
            Log::error('Failed to send password reset OTP to '.self::maskEmail($user->email).': '.$exception->getMessage(), ['exception' => $exception]);

            return back()
                ->withErrors(['email' => 'Unable to send the password reset email. Please try again later.'])
                ->withInput($request->only('email'));
        }

        $request->session()->put(self::SESSION_EMAIL, $user->email);
        $request->session()->forget(self::SESSION_VERIFIED_AT);

        return redirect()->route('password.otp.verify');
    }

    /**
     * GET /forgot-password/verify
     */
    public function showVerifyForm(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        $record = PasswordResetOtpModel::where('email', $email)->latest('last_sent_at')->first();

        return view('auth.verify-otp', [
            'maskedEmail' => self::maskEmail($email),
            'resendLock' => $record && $record->last_sent_at
                ? $record->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->getTimestamp()
                : 0,
        ]);
    }

    /**
     * POST /forgot-password/verify
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'otp.required' => 'Invalid verification code.',
            'otp.regex' => 'Invalid verification code.',
        ]);

        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        $record = PasswordResetOtpModel::where('email', $email)->latest('last_sent_at')->first();

        if (! $record) {
            return back()->withErrors(['otp' => 'Invalid verification code.']);
        }

        if ($record->isExpired()) {
            $record->delete();

            return back()->withErrors(['otp' => 'Your verification code has expired. Please request a new code.']);
        }

        if ($record->isVerified()) {
            return back()->withErrors(['otp' => 'Invalid verification code.']);
        }

        if ($record->hasExhaustedAttempts(self::MAX_ATTEMPTS)) {
            $record->delete();

            return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new OTP.']);
        }

        if (! Hash::check($validated['otp'], $record->otp_hash)) {
            $record->increment('attempts');
            if ($record->hasExhaustedAttempts(self::MAX_ATTEMPTS)) {
                $record->delete();

                return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new OTP.']);
            }

            return back()->withErrors(['otp' => 'Invalid verification code.']);
        }

        $record->update(['verified_at' => now()]);

        $request->session()->put(self::SESSION_VERIFIED_AT, now());

        return redirect()->route('password.reset');
    }

    /**
     * POST /forgot-password/resend
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $request->session()->forget([self::SESSION_EMAIL, self::SESSION_VERIFIED_AT]);

            return redirect()->route('password.request');
        }

        $record = PasswordResetOtpModel::where('email', $email)->latest('last_sent_at')->first();

        $nextAllowedAt = $record && $record->last_sent_at
            ? $record->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)
            : null;

        $remaining = $nextAllowedAt ? $nextAllowedAt->getTimestamp() - now()->getTimestamp() : 0;

        if ($remaining > 0) {
            $seconds = max(1, (int) ceil($remaining));

            return back()->withErrors(['otp' => "Please wait {$seconds} second".($seconds === 1 ? '' : 's').' before requesting a new code.']);
        }

        $result = $this->issueOtp($user);

        try {
            Mail::to($user->email)->send(new PasswordResetOtp($result['otp']));
        } catch (Throwable $exception) {
            $result['record']->delete();
            Log::error('Failed to resend password reset OTP to '.self::maskEmail($user->email).': '.$exception->getMessage(), ['exception' => $exception]);

            return back()->withErrors(['otp' => 'Unable to resend the verification email. Please try again later.']);
        }

        $request->session()->forget(self::SESSION_VERIFIED_AT);

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * GET /reset-password (OTP verified first, middleware enforced)
     */
    public function showResetForm(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', ['maskedEmail' => self::maskEmail($email)]);
    }

    /**
     * POST /reset-password
     */
    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $request->session()->forget([self::SESSION_EMAIL, self::SESSION_VERIFIED_AT]);

            return redirect()->route('password.request');
        }

        $user->password = $validated['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();

        PasswordResetOtpModel::where('email', $user->email)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $request->session()->forget([self::SESSION_EMAIL, self::SESSION_VERIFIED_AT]);
        $request->session()->regenerate();

        return redirect()->route('login')
            ->with('success', 'Your password has been successfully reset. You can now log in with your new password.');
    }

    /**
     * Generate a fresh cryptographically random 6-digit OTP, persist only its
     * hash (invalidating any previous code for the same address) and return
     * the plain code so the caller can email it.
     *
     * @return array{otp: string, record: PasswordResetOtpModel}
     */
    protected function issueOtp(User $user): array
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = PasswordResetOtpModel::updateOrCreate(
            ['email' => $user->email],
            [
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'last_sent_at' => now(),
                'verified_at' => null,
            ]
        );

        return ['otp' => $otp, 'record' => $record];
    }

    /**
     * Mask an email for display, e.g. j***@gmail.com. Never reveals the full
     * address.
     */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($name === '') {
            return '***@'.$domain;
        }

        $stars = match (true) {
            strlen($name) >= 3 => 3,
            strlen($name) === 2 => 2,
            default => 1,
        };

        return substr($name, 0, 1).str_repeat('*', $stars).'@'.$domain;
    }
}