<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_login_page_renders_the_redesigned_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('SNNHS SPORTS ACTIVITY HUB')
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
            ->assertSee("Enter your email address and we'll send you a link to reset your password.", false)
            ->assertSee('Enter your email')
            ->assertSee('Send Password Reset Link')
            ->assertSee('Back to Login');
    }

    public function test_reset_link_is_emailed_and_reveals_nothing_for_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'coach@example.com', 'password' => 'password', 'role' => 'Student']);

        $this->post(route('password.email'), ['email' => 'coach@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', 'If an account exists with that email, a password reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', 'If an account exists with that email, a password reset link has been sent.');

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_reset_link_request_requires_a_valid_email(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_password_can_be_reset_with_the_emailed_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => 'password', 'role' => 'Student']);

        $this->post(route('password.email'), ['email' => 'student@example.com']);

        $resetUrl = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$resetUrl) {
            $resetUrl = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->get($resetUrl)
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Confirm new password');

        $token = basename(parse_url($resetUrl, PHP_URL_PATH));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'student@example.com',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been reset successfully. You can now log in with your new password.');

        $this->assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'student@example.com']);

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

    public function test_reset_requires_a_matching_confirmation(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => 'password', 'role' => 'Student']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'student@example.com',
            'password' => 'new-strong-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_rejects_invalid_and_expired_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => 'password', 'role' => 'Student']);

        $this->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => 'student@example.com',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $token = Password::broker()->createToken($user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'student@example.com',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_token_is_hashed_in_storage(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => 'password', 'role' => 'Student']);
        $token = Password::broker()->createToken($user);

        $storedToken = DB::table('password_reset_tokens')->where('email', 'student@example.com')->value('token');

        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));
    }
}
