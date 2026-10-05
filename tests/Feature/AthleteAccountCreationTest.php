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

    public function test_create_account_form_prefills_the_application_email(): void
    {
        $application = $this->approvedApplication();

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->get(route('athletes.create', $application))
            ->assertOk()
            ->assertSee('Create Athlete Account')
            ->assertSee('ana@example.com')
            ->assertSee('value="ana@example.com"', false)
            ->assertSee('Ana Santos');
    }

    public function test_create_account_form_is_unavailable_before_approval(): void
    {
        $application = $this->approvedApplication(['status' => 'Pending']);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->get(route('athletes.create', $application))
            ->assertOk()
            ->assertSee('Approve this application before creating the athlete account.');
    }

    public function test_admin_can_correct_the_email_before_creating_the_account(): void
    {
        $application = $this->approvedApplication();

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->post(route('athletes.store'), [
                'application_id' => $application->id,
                'email' => 'ana.corrected@example.com',
                'username' => 'ana.santos',
                'password' => 'temporary-password',
                'password_confirmation' => 'temporary-password',
            ])
            ->assertRedirect(route('athletes.index'));

        $this->assertDatabaseHas('users', ['email' => 'ana.corrected@example.com', 'student_id' => '2026-102']);
    }

    public function test_account_is_not_created_when_the_email_already_exists(): void
    {
        $application = $this->approvedApplication();
        User::factory()->create(['role' => 'Student', 'email' => 'ana@example.com', 'student_id' => '2026-900']);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->from(route('athletes.create', $application))
            ->post(route('athletes.store'), [
                'application_id' => $application->id,
                'username' => 'ana.santos',
                'password' => 'temporary-password',
                'password_confirmation' => 'temporary-password',
            ])
            ->assertRedirect(route('athletes.create', $application))
            ->assertSessionHasErrors('email');

        $this->assertSame('An account with this email already exists.', session('errors')->first('email'));
        $this->assertDatabaseMissing('users', ['username' => 'ana.santos']);
        $this->assertNull($application->fresh()->athlete_id);
    }

    public function test_account_creation_is_blocked_when_the_application_has_no_email(): void
    {
        $application = $this->approvedApplication(['email' => '']);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->from(route('athletes.create', $application))
            ->post(route('athletes.store'), [
                'application_id' => $application->id,
                'username' => 'ana.santos',
                'password' => 'temporary-password',
                'password_confirmation' => 'temporary-password',
            ])
            ->assertRedirect(route('athletes.create', $application))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['username' => 'ana.santos']);
    }

    public function test_account_cannot_be_created_twice_for_the_same_application(): void
    {
        $application = $this->approvedApplication();
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'ana@example.com', 'student_id' => '2026-102']);
        $application->update(['athlete_id' => $athlete->id]);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->from(route('athletes.create', $application))
            ->post(route('athletes.store'), [
                'application_id' => $application->id,
                'username' => 'ana.second',
                'password' => 'temporary-password',
                'password_confirmation' => 'temporary-password',
            ])
            ->assertRedirect(route('athletes.create', $application))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['username' => 'ana.second']);
    }

    public function test_account_creation_requires_an_approved_application(): void
    {
        $application = $this->approvedApplication(['status' => 'Rejected']);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->post(route('athletes.store'), [
                'application_id' => $application->id,
                'username' => 'ana.santos',
                'password' => 'temporary-password',
                'password_confirmation' => 'temporary-password',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['username' => 'ana.santos']);
    }

    private function approvedApplication(array $overrides = []): Application
    {
        $sport = Sport::firstOrCreate(['name' => 'Basketball'], ['classification' => 'Team', 'description' => 'Court sport']);

        return Application::create([
            'name' => 'Ana Santos', 'student_id' => '2026-102', 'grade' => 'Grade 11', 'gender' => 'Female',
            'email' => 'ana@example.com', 'sport' => 'Basketball', 'sport_id' => $sport->id, 'status' => 'Approved',
            'medical_certificate_path' => 'medical.pdf', 'birth_certificate_path' => 'birth.pdf', 'parent_consent_path' => 'consent.pdf',
            ...$overrides,
        ]);
    }
}
