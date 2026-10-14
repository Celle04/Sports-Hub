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

class AdminAssistedPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, ThrottleRequests::class]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'Administrator',
        ]);
    }

    private function createStudent(string $email = 'student@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'username' => str_replace('@example.com', '', $email),
            'password' => 'password',
            'role' => 'Student',
            'status' => 'Active',
        ]);
    }

    private function initiateReset(User $admin, User $student): string
    {
        Notification::fake();

        $this->actingAs($admin)
            ->from(route('athletes.index'))
            ->post(route('athletes.password.reset', $student))
            ->assertRedirect(route('athletes.index'))
            ->assertSessionHas('success');

        $token = null;

        Notification::assertSentTo($student, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        return (string) $token;
    }

    public function test_an_authorized_administrator_can_initiate_a_reset_for_a_student(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        Notification::fake();

        $this->actingAs($admin)
            ->from(route('athletes.index'))
            ->post(route('athletes.password.reset', $student))
            ->assertRedirect(route('athletes.index'))
            ->assertSessionHas('success');

        Notification::assertSentTo($student, ResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $student->email]);
    }

    public function test_a_student_cannot_access_the_administrative_reset_action(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();
        $target = $this->createStudent('other@example.com');

        $this->actingAs($student)
            ->post(route('athletes.password.reset', $target))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('athletes.index'))
            ->assertForbidden();
    }

    public function test_an_administrator_cannot_reset_another_administrator_password(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = User::factory()->create([
            'email' => 'admin2@example.com',
            'username' => 'admin2',
            'password' => 'password',
            'role' => 'Administrator',
        ]);

        $this->actingAs($admin)
            ->post(route('athletes.password.reset', $otherAdmin))
            ->assertForbidden();
    }

    public function test_the_reset_link_expires_according_to_configured_rules(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $token = $this->initiateReset($admin, $student);

        $this->travel(61)->minutes();

        $this->get(route('password.reset', ['token' => $token, 'email' => $student->email]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), [
            'email' => $student->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $student->fresh()->password));
    }

    public function test_an_invalid_or_expired_token_cannot_reset_a_password(): void
    {
        $student = $this->createStudent();

        $this->get(route('password.reset', ['token' => 'not-a-real-token', 'email' => $student->email]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), [
            'email' => $student->email,
            'token' => 'not-a-real-token',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $student->fresh()->password));
    }

    public function test_a_valid_reset_allows_the_student_to_set_a_new_password(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $token = $this->initiateReset($admin, $student);

        $this->post(route('password.update'), [
            'email' => $student->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $fresh = $student->fresh();

        $this->assertTrue(Hash::check('new-strong-password', $fresh->password));
        $this->assertNotSame('new-strong-password', $fresh->password);

        $this->post(route('login.submit'), [
            'username' => $student->email,
            'password' => 'new-strong-password',
            'role' => 'Student',
        ])->assertRedirect(route('student.dashboard'));

        $this->post(route('logout'));
        $this->post(route('login.submit'), [
            'username' => $student->email,
            'password' => 'password',
            'role' => 'Student',
        ])->assertRedirect(route('login'));
    }

    public function test_old_password_no_longer_works_after_successful_reset(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $token = $this->initiateReset($admin, $student);

        $this->post(route('password.update'), [
            'email' => $student->email,
            'token' => $token,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('login'));

        $this->post(route('login.submit'), [
            'username' => $student->email,
            'password' => 'password',
            'role' => 'Student',
        ])->assertRedirect(route('login'));
    }

    public function test_email_delivery_failures_are_handled_correctly(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $this->mock(Factory::class, function ($mock) {
            $mock->shouldReceive('mailer')->andThrow(new \RuntimeException('smtp connection failed'));
        });

        $this->actingAs($admin)
            ->from(route('athletes.index'))
            ->post(route('athletes.password.reset', $student))
            ->assertRedirect(route('athletes.index'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $student->email]);
        $this->assertTrue(Hash::check('password', $student->fresh()->password));
    }

    public function test_existing_login_and_roles_still_work(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $this->post(route('login.submit'), [
            'username' => 'admin@example.com',
            'password' => 'password',
            'role' => 'Administrator',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'username' => 'student@example.com',
            'password' => 'password',
            'role' => 'Student',
        ])->assertRedirect(route('student.dashboard'));
    }

    public function test_the_login_page_shows_the_assistance_message(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot your password? Please contact the Sports Coordinator or Admin for assistance.');
    }

    public function test_assist_action_shows_in_athlete_management(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent();

        $this->actingAs($admin)
            ->get(route('athletes.index'))
            ->assertOk()
            ->assertSee('Assist with Password Reset');
    }
}
