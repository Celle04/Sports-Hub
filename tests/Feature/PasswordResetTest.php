<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, ThrottleRequests::class]);
    }

    private function createUser(string $email = 'student@example.com', string $role = 'Student'): User
    {
        return User::factory()->create([
            'email' => $email,
            'username' => $role === 'Administrator' ? 'admin' : 'student',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    /**
     * Go through the real request flow (broker + notification) and return the
     * plain reset token that was just emailed to the user.
     */
    private function requestResetToken(User $user): string
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);
        $this->assertNotEmpty($token);

        return (string) $token;
    }

    public function test_login_page_displays_the_forgot_password_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('SNNHS SPORTSHUB')
            ->assertSee('Sports Management System')
            ->assertSee('Enter your email')
            ->assertSee('Enter your password')
            ->assertSee('Remember me')
            ->assertSee('Forgot your password? Please contact the Sports Coordinator or Admin for assistance.')
            ->assertSee('Login')
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('name="remember"', false);
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee("Enter your registered email address and we'll send you a link to reset your password.", false)
            ->assertSee('Enter your email')
            ->assertSee('Send Reset Link')
            ->assertSee('Back to Login');
    }

    public function test_a_valid_email_sends_the_reset_notification_through_the_broker(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token, $user) {
            $token = $notification->token;

            $mail = $notification->toMail($user);

            $this->assertSame('Reset Your SportsHub Password', $mail->subject);
            $this->assertSame('Hello,', $mail->greeting);
            $this->assertSame('Reset Password', $mail->actionText);
            $this->assertStringStartsWith(url('/'), $mail->actionUrl);
            $this->assertStringContainsString('token='.$notification->token, $mail->actionUrl);
            $this->assertStringContainsString('email='.urlencode($user->email), $mail->actionUrl);
            $this->assertStringContainsString('SNNHS Sports Activity Hub', collect($mail->introLines)->implode(' '));
            $this->assertStringContainsString('60 minutes', collect($mail->outroLines)->implode(' '));

            return true;
        });

        $this->assertNotEmpty($token);

        // The broker persists only a hash of the token, never the plain value.
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotNull($stored);
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }

    public function test_an_unknown_email_gets_a_generic_reply_and_reveals_nothing(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_known_and_unknown_addresses_receive_the_identical_reply(): void
    {
        Notification::fake();
        $known = $this->createUser();

        $this->post(route('password.email'), ['email' => $known->email])
            ->assertRedirect(route('password.request'));

        $knownReply = session('success');
        $this->assertNotNull($knownReply);

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('password.request'));

        $this->assertSame($knownReply, session('success'));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_a_reset_request_requires_a_valid_email(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_reset_form_without_a_token_or_email_is_rejected(): void
    {
        $this->get(route('password.reset'))->assertRedirect(route('password.request'));
    }

    public function test_reset_form_renders_for_a_valid_token(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $token = $this->requestResetToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee($user->email)
            ->assertSee('name="token"', false)
            ->assertSee('Reset Password')
            ->assertSee('Back to Login');
    }

    public function test_reset_form_validates_required_fields(): void
    {
        $this->post(route('password.update'), [])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email', 'token', 'password']);
    }

    public function test_reset_rejects_a_password_confirmation_mismatch(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $token = $this->requestResetToken($user);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'different-password',
        ])->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_rejects_a_weak_password(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $token = $this->requestResetToken($user);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_an_invalid_token_is_rejected_on_open_and_on_submit(): void
    {
        $user = $this->createUser();

        $this->get(route('password.reset', ['token' => 'not-a-real-token', 'email' => $user->email]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_an_expired_token_is_rejected_on_open_and_on_submit(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $token = $this->requestResetToken($user);

        $this->travel(61)->minutes();

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_valid_token_resets_the_password_hashes_it_and_invalidates_the_token(): void
    {
        Notification::fake();
        $user = $this->createUser('athlete@example.com');
        $token = $this->requestResetToken($user);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been successfully reset. You can now log in with your new password.');

        // A reset never signs the visitor in; the login page still owns that.
        $this->assertGuest();

        $fresh = $user->fresh();

        $this->assertSame('Student', $fresh->role);
        $this->assertNotSame('new-strong-password', $fresh->password);
        $this->assertTrue(Hash::isHashed($fresh->password));
        $this->assertTrue(Hash::check('new-strong-password', $fresh->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        // The athlete signs in with the new password and lands on the portal.
        $this->post(route('login.submit'), [
            'username' => 'athlete@example.com',
            'password' => 'new-strong-password',
            'role' => 'Student',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_used_reset_token_cannot_be_reused(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $token = $this->requestResetToken($user);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('login'));

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
    }

    public function test_an_administrator_can_reset_and_still_logs_in_as_an_admin(): void
    {
        Notification::fake();
        $admin = $this->createUser('admin@example.com', 'Administrator');
        $token = $this->requestResetToken($admin);

        $this->post(route('password.update'), [
            'email' => $admin->email,
            'token' => $token,
            'password' => 'admin-new-password',
            'password_confirmation' => 'admin-new-password',
        ])->assertRedirect(route('login'));

        $this->assertSame('Administrator', $admin->fresh()->role);
        $this->assertTrue(Hash::check('admin-new-password', $admin->fresh()->password));

        $this->post(route('login.submit'), [
            'username' => 'admin@example.com',
            'password' => 'admin-new-password',
            'role' => 'Administrator',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_mail_delivery_failure_shows_a_friendly_error_and_cleans_up_the_token(): void
    {
        $user = $this->createUser();

        $this->mock(Factory::class, function ($mock) {
            $mock->shouldReceive('mailer')->andThrow(new \RuntimeException('smtp connection failed'));
        });

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
