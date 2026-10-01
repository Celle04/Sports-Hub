<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_search_and_view_application_details(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'School volleyball program']);
        $application = $this->application($sport, ['name' => 'Maria Santos', 'status' => 'Pending']);

        $this->actingAs($admin)->get(route('admin.applications', ['search' => 'Maria', 'status' => 'Pending']))
            ->assertOk()
            ->assertSee('Maria Santos');
        $this->actingAs($admin)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Application Review')
            ->assertSee('Medical Certificate');
    }

    public function test_application_cannot_be_approved_until_documents_are_verified(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $application = $this->application(null, ['medical_certificate_path' => 'medical.pdf', 'birth_certificate_path' => 'birth.pdf', 'parent_consent_path' => 'consent.pdf']);

        $response = $this->actingAs($admin)->post(route('applications.approve', $application));

        $response->assertStatus(422);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'Pending']);
    }

    public function test_verified_documents_allow_approval_and_rejection_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $application = $this->application(null, ['medical_certificate_path' => 'medical.pdf', 'birth_certificate_path' => 'birth.pdf', 'parent_consent_path' => 'consent.pdf']);

        foreach (['medical', 'birth', 'consent'] as $document) {
            $this->actingAs($admin)->post(route('applications.documents.verify', [$application, $document]));
        }
        $this->actingAs($admin)->post(route('applications.approve', $application))->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'Approved']);

        $rejectedApplication = $this->application(null, ['name' => 'Rejected Athlete']);
        $this->actingAs($admin)->post(route('applications.reject', $rejectedApplication))->assertSessionHasErrors('rejection_reason');
        $this->actingAs($admin)->post(route('applications.reject', $rejectedApplication), ['rejection_reason' => 'Incomplete medical clearance'])->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $rejectedApplication->id, 'status' => 'Rejected', 'rejection_reason' => 'Incomplete medical clearance']);
    }

    private function application(?Sport $sport, array $overrides = []): Application
    {
        return Application::create(array_merge([
            'name' => 'Test Applicant',
            'student_id' => '2026-3000',
            'grade' => 'Grade 10',
            'gender' => 'Female',
            'email' => 'applicant@example.com',
            'sport' => $sport?->name ?? 'General',
            'sport_id' => $sport?->id,
            'status' => 'Pending',
        ], $overrides));
    }
}
