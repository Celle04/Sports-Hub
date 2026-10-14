<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    /**
     * The only reply a /forgot-password submission ever gets. Registered and
     * unknown addresses receive this exact sentence, so the response can
     * never confirm whether an account exists.
     */
    protected const REQUEST_SUCCESS_MESSAGE = 'If an account exists with that email, a password reset link has been sent. Check your inbox and spam folder, then follow the link to choose a new password.';

    /**
     * Shown for expired, used, forged or unknown reset links.
     */
    protected const INVALID_LINK_MESSAGE = 'That password reset link is invalid or has expired. Please request a new one.';

    /**
     * Shown when the mail transport itself fails.
     */
    protected const DELIVERY_ERROR_MESSAGE = 'Unable to send the password reset email. Please try again later.';

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
     * Asks Laravel's password broker to create the reset token and deliver
     * the branded notification. Token generation, hashing, expiry and the
     * per-user resend throttle all stay inside the broker; nothing about the
     * link is built here. Every outcome other than a delivery failure gets
     * the same generic reply so the endpoint cannot be used to enumerate
     * registered addresses.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = (string) $request->input('email');

        try {
            Password::broker()->sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            // The token was created but the mail never left the server. Drop
            // it so a link nobody received cannot be used later, and log only
            // a masked address - never the token or the SMTP credentials.
            DB::table($this->tokenTable())->where('email', $email)->delete();

            Log::error('Password reset email delivery failed for '.self::maskEmail($email).': '.$exception->getMessage());

            return redirect()->route('password.request')
                ->withErrors(['email' => self::DELIVERY_ERROR_MESSAGE])
                ->withInput($request->only('email'));
        }

        return redirect()->route('password.request')
            ->with('success', self::REQUEST_SUCCESS_MESSAGE)
            ->withInput($request->only('email'));
    }

    /**
     * GET /reset-password?token=...&email=...
     *
     * The link from the email only opens the form when the broker still
     * recognises an unexpired, unused token for that address. Expired, used
     * and forged links are sent back to the request form with a safe message
     * instead of rendering a form that could never succeed.
     */
    public function showResetForm(Request $request): View|RedirectResponse
    {
        $token = trim((string) $request->query('token'));
        $email = trim((string) $request->query('email'));

        if ($token === '' || $email === '') {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => self::INVALID_LINK_MESSAGE]);
        }

        return view('auth.reset-password', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    /**
     * POST /reset-password
     *
     * Server-side validation first, then the password broker performs the
     * actual reset: it re-checks the token, hashes the new password through
     * the User model's cast, and invalidates the token in the same call.
     * The visitor is never signed in automatically - the existing login page
     * keeps owning role-based redirects.
     */
    public function reset(Request $request): RedirectResponse
    {
        $token = trim((string) $request->input('token'));
        $email = trim((string) $request->input('email'));

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'The new passwords do not match.',
        ]);

        if ($validator->fails()) {
            $redirect = ($token !== '' && $email !== '')
                ? redirect()->route('password.reset', ['token' => $token, 'email' => $email])
                : redirect()->route('password.request');

            return $redirect
                ->withErrors($validator->errors())
                ->withInput($request->only('email'));
        }

        $response = Password::broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $validator->validated()['password']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])
                    ->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($response === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Your password has been successfully reset. You can now log in with your new password.');
        }

        // INVALID_USER / INVALID_TOKEN: the link is expired, already used or
        // not one we issued. The message stays the same in every case so a
        // probe cannot tell which of those it hit.
        return redirect()->route('password.request')
            ->withErrors(['email' => self::INVALID_LINK_MESSAGE]);
    }

    /**
     * Table the configured password broker stores reset tokens in.
     */
    protected function tokenTable(): string
    {
        $broker = (string) config('auth.defaults.passwords');

        return (string) config("auth.passwords.{$broker}.table", 'password_reset_tokens');
    }

    /**
     * Mask an email for logs and display, e.g. j***@gmail.com. The full
     * address is never written to the log.
     */
    protected static function maskEmail(string $email): string
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
