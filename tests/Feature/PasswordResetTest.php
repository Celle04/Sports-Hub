<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, ThrottleRequests::class]);
    }

    private function createUser(string $email = 'student@example.com', string $password = 'password'): User
    {
        return User::factory()->create([
            'email' => $email,
            'username' => 'student',
            'password' => $password,
            'role' => 'Student',
        ]);
    }

    /**
     * Request an OTP and return the plain code captured from the sent mail.
     */
    private function requestOtpFor(User $user): string
    {
        $sent = [];

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.otp.verify'));

        Mail::assertSent(PasswordResetOtp::class, function (PasswordResetOtp $mail) use (&$sent) {
            $sent['otp'] = $mail->otp;
            $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->otp);

            return true;
        });

        return $sent['otp'];
    }

    private function wrongOtpFor(string $otp): string
    {
        return $otp === '000000' ? '111111' : '000000';
    }

    public function test_login_page_renders_the_redesigned_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('SNNHS SPORTSHUB')
            ->assertSee('Sports Management System')
            ->assertSee('Enter your email')
            ->assertSee('Enter your password')
            ->assertSee('Remember me')
            ->assertSee('Forgot Password?')
            ->assertSee('Login')
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('name="remember"', false);
    }

    public function test_login_requires_a_username_and_password(): void
    {
        $this->post(route('login.submit'), ['role' => 'Administrator'])
            ->assertSessionHasErrors(['username', 'password']);
    }

    public function test_login_reports_invalid_credentials_without_hinting_at_the_account(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin', 'password' => 'password', 'role' => 'Administrator']);

        $this->post(route('login.submit'), [
            'username' => 'admin@example.com',
            'password' => 'wrong-password',
            'role' => 'Administrator',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_honours_the_remember_me_checkbox(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin', 'password' => 'password', 'role' => 'Administrator']);

        $this->post(route('login.submit'), [
            'username' => 'admin@example.com',
            'password' => 'password',
            'role' => 'Administrator',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_without_remember_me_does_not_issue_a_token(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => 'password', 'role' => 'Student', 'remember_token' => null]);

        $this->post(route('login.submit'), [
            'username' => 'student@example.com',
            'password' => 'password',
            'role' => 'Student',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee("we'll send you a 6-digit verification code to reset your password.", false)
            ->assertSee('Enter your email')
            ->assertSee('Send OTP')
            ->assertSee('Back to Login');
    }

    public function test_sending_the_otp_emails_a_code_and_goes_to_the_verification_page(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.otp.verify'))
            ->assertSessionHas('password_reset_email', $user->email);

        Mail::assertSent(PasswordResetOtp::class, function (PasswordResetOtp $mail) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->otp);

            return true;
        });

        $this->assertDatabaseHas('password_reset_otps', ['email' => $user->email]);
    }

    public function test_a_mailer_failure_shows_a_friendly_error_and_cleans_up_the_otp_record(): void
    {
        $user = $this->createUser();

        Mail::shouldReceive('to')
            ->once()
            ->andReturnUsing(fn () => throw new \RuntimeException('smtp connection failed'));

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('password_reset_email');

        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
    }

    public function test_unknown_email_keeps_the_generic_reply_and_reveals_nothing(): void
    {
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', 'If an account exists with that email, a password reset link has been sent.')
            ->assertSessionMissing('password_reset_email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);

        $this->get(route('password.otp.verify'))->assertRedirect(route('password.request'));
    }

    public function test_otp_request_requires_a_valid_email(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_verification_page_is_only_reachable_with_an_active_request(): void
    {
        $this->get(route('password.otp.verify'))->assertRedirect(route('password.request'));
    }

    public function test_the_verification_page_masks_the_email_and_never_reveals_the_code(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);

        $this->get(route('password.otp.verify'))
            ->assertOk()
            ->assertSee('Verify your email')
            ->assertSee('s***@example.com')
            ->assertDontSee($otp)
            ->assertSee('data-otp-boxes', false)
            ->assertSee('Resend OTP');

        $stored = DB::table('password_reset_otps')->where('email', $user->email)->value('otp_hash');

        $this->assertNotSame($otp, $stored);
        $this->assertTrue(Hash::check($otp, $stored));
    }

    public function test_the_full_flow_verifies_the_otp_resets_the_password_and_logs_in(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);

        $this->post(route('password.otp.check'), ['otp' => $otp])
            ->assertRedirect(route('password.reset'));

        $this->get(route('password.reset'))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Choose a new password')
            ->assertSee('s***@example.com');

        $this->post(route('password.update'), [
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been successfully reset. You can now log in with your new password.');

        $this->assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);

        $this->post(route('login.submit'), [
            'username' => 'student@example.com',
            'password' => 'new-strong-password',
            'role' => 'Student',
        ])->assertRedirect(route('student.dashboard'));

        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'username' => 'student@example.com',
            'password' => 'password',
            'role' => 'Student',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
    }

    public function test_wrong_otp_is_rejected_and_five_failures_invalidate_the_code(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);
        $wrong = $this->wrongOtpFor($otp);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post(route('password.otp.check'), ['otp' => $wrong])
                ->assertRedirect()
                ->assertSessionHasErrors('otp', 'Invalid verification code.');
        }

        $this->assertDatabaseHas('password_reset_otps', ['email' => $user->email, 'attempts' => 4]);

        $this->post(route('password.otp.check'), ['otp' => $wrong])
            ->assertRedirect()
            ->assertSessionHasErrors('otp', 'Too many incorrect attempts. Please request a new OTP.');

        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);

        $this->post(route('password.otp.check'), ['otp' => $otp])
            ->assertRedirect()
            ->assertSessionHasErrors('otp', 'Invalid verification code.');
    }

    public function test_an_expired_otp_is_declined_and_deleted(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);

        $this->travel(6)->minutes();

        $this->post(route('password.otp.check'), ['otp' => $otp])
            ->assertRedirect()
            ->assertSessionHasErrors('otp', 'Your verification code has expired. Please request a new code.');

        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
    }

    public function test_resend_is_cooldown_limited_and_invalidates_the_previous_code(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $firstOtp = $this->requestOtpFor($user);

        $this->post(route('password.otp.resend'))
            ->assertRedirect()
            ->assertSessionHasErrors('otp');

        $this->travel(61)->seconds();

        $this->post(route('password.otp.resend'))
            ->assertRedirect()
            ->assertSessionHas('success', 'A new verification code has been sent to your email.');

        Mail::assertSent(PasswordResetOtp::class, 2);

        $mails = collect(Mail::sent(PasswordResetOtp::class));
        $secondOtp = $mails->last()->otp;
        $this->assertNotNull($secondOtp);
        $this->assertNotSame($firstOtp, $secondOtp);

        $this->post(route('password.otp.check'), ['otp' => $firstOtp])
            ->assertRedirect()
            ->assertSessionHasErrors('otp', 'Invalid verification code.');
    }

    public function test_the_reset_page_is_gated_behind_a_verified_otp(): void
    {
        $this->get(route('password.reset'))->assertRedirect(route('password.request'));
        $this->post(route('password.update'), ['password' => 'new-strong-password', 'password_confirmation' => 'new-strong-password'])
            ->assertRedirect(route('password.request'));
    }

    public function test_verifying_then_requesting_a_new_otp_revokes_the_gate(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);

        $this->post(route('password.otp.check'), ['otp' => $otp])->assertRedirect(route('password.reset'));

        $this->post(route('password.otp.resend'))->assertRedirect();
        $this->travel(61)->seconds();
        $this->post(route('password.otp.resend'))->assertRedirect();

        $this->get(route('password.reset'))->assertRedirect(route('password.request'));
    }

    public function test_reset_requires_a_matching_confirmation(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);

        $this->post(route('password.otp.check'), ['otp' => $otp])->assertRedirect(route('password.reset'));

        $this->post(route('password.update'), [
            'password' => 'new-strong-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_rejects_a_weak_password(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $otp = $this->requestOtpFor($user);

        $this->post(route('password.otp.check'), ['otp' => $otp])->assertRedirect(route('password.reset'));

        $this->post(route('password.update'), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}