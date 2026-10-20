<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
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

    public function test_an_authorized_administrator_can_reset_a_students_password(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)
            ->from(route('athletes.index'))
            ->post(route('athletes.password.reset', $student), [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['temporary_password', 'athlete_name']);

        $this->assertSame($student->name, $response->json('athlete_name'));
        $this->assertNotNull($response->json('temporary_password'));
        $this->assertNotSame('password', $student->fresh()->password);
        $this->assertTrue($student->fresh()->needsPasswordChange());
    }

    public function test_temporary_password_is_stored_only_as_a_hash(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)
            ->post(route('athletes.password.reset', $student), [], ['Accept' => 'application/json']);

        $temporaryPassword = $response->json('temporary_password');
        $fresh = $student->fresh();

        $this->assertNotNull($temporaryPassword);
        $this->assertNotSame($temporaryPassword, $fresh->password);
        $this->assertTrue(Hash::check($temporaryPassword, $fresh->password));
    }

    public function test_temporary_password_allows_login_then_forces_password_change(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)
            ->post(route('athletes.password.reset', $student), [], ['Accept' => 'application/json']);
        $temporaryPassword = $response->json('temporary_password');

        // Logging in with the temporary password redirects to change password.
        $this->post(route('login.submit'), [
            'username' => $student->email,
            'password' => $temporaryPassword,
            'role' => 'Student',
        ])->assertRedirect(route('student.change-password'));

        // Protected athlete pages are blocked until the password is changed.
        $this->actingAs($student->fresh())
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.change-password'));
    }

    public function test_changing_the_password_clears_the_forced_change_requirement(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)
            ->post(route('athletes.password.reset', $student), [], ['Accept' => 'application/json']);
        $temporaryPassword = $response->json('temporary_password');

        // Logging in with the temporary password authenticates the student.
        $this->post(route('login.submit'), [
            'username' => $student->email,
            'password' => $temporaryPassword,
            'role' => 'Student',
        ])->assertRedirect(route('student.change-password'));

        $this->post(route('student.change-password.update'), [
            'current_password' => $temporaryPassword,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertFalse($student->fresh()->needsPasswordChange());
        $this->assertTrue(Hash::check('new-strong-password', $student->fresh()->password));

        // The dashboard is now accessible again.
        $this->actingAs($student->fresh())
            ->get(route('student.dashboard'))
            ->assertOk();
    }

    public function test_the_new_password_must_differ_from_the_temporary_password(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)
            ->post(route('athletes.password.reset', $student), [], ['Accept' => 'application/json']);
        $temporaryPassword = $response->json('temporary_password');

        $this->post(route('login.submit'), [
            'username' => $student->email,
            'password' => $temporaryPassword,
            'role' => 'Student',
        ]);

        $this->post(route('student.change-password.update'), [
            'current_password' => $temporaryPassword,
            'password' => $temporaryPassword,
            'password_confirmation' => $temporaryPassword,
        ])->assertSessionHasErrors('password');
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

    public function test_an_administrator_cannot_reset_another_administrators_password(): void
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

    public function test_an_administrator_cannot_reset_their_own_password_via_the_athlete_action(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('athletes.password.reset', $admin))
            ->assertForbidden();
    }

    public function test_a_missing_athlete_is_handled_safely(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('athletes.password.reset', 999999))
            ->assertNotFound();
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

    public function test_the_password_reset_action_appears_in_athlete_management(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent();

        $this->actingAs($admin)
            ->get(route('athletes.index'))
            ->assertOk()
            ->assertSee('Reset Password');
    }
}
