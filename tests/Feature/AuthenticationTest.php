<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_log_in_with_database_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'Administrator',
        ]);

        $response = $this->post(route('login.submit'), [
            'username' => 'admin@example.com',
            'password' => 'password',
            'role' => 'Administrator',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_student_can_log_in_with_database_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'password',
            'role' => 'Student',
        ]);

        $response = $this->post(route('login.submit'), [
            'username' => 'student@example.com',
            'password' => 'password',
            'role' => 'Student',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_student_can_log_in_with_username(): void
    {
        User::factory()->create([
            'email' => 'student2@example.com',
            'username' => 'juan.delacruz',
            'password' => 'password',
            'role' => 'Student',
        ]);

        $response = $this->post(route('login.submit'), [
            'username' => 'juan.delacruz',
            'password' => 'password',
            'role' => 'Student',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $response = $this->post(route('login.submit'), [
            'username' => 'unknown@example.com',
            'password' => 'wrong-password',
            'role' => 'Student',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_portal_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}