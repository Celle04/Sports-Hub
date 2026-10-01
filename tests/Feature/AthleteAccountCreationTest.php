<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AthleteAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_an_athlete_account_only_from_a_complete_approved_application(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team', 'description' => 'Court sport']);
        $application = Application::create([
            'name' => 'Ana Santos', 'student_id' => '2026-102', 'grade' => 'Grade 11', 'gender' => 'Female',
            'email' => 'ana@example.com', 'sport' => 'Basketball', 'sport_id' => $sport->id, 'status' => 'Approved',
            'medical_certificate_path' => 'medical.pdf', 'birth_certificate_path' => 'birth.pdf', 'parent_consent_path' => 'consent.pdf',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->post(route('athletes.store'), ['application_id' => $application->id, 'username' => 'ana.santos', 'password' => 'temporary-password', 'password_confirmation' => 'temporary-password'])
            ->assertRedirect(route('athletes.index'));

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'username' => 'ana.santos', 'student_id' => '2026-102', 'sport_id' => $sport->id, 'role' => 'Student']);
    }

    public function test_student_cannot_create_athlete_accounts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Student']))
            ->post(route('athletes.store'))
            ->assertForbidden();
    }
}
