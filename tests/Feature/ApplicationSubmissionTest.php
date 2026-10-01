<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('private');
        Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court game']);
    }

    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'student_id' => '2026-0001',
            'grade' => 'Grade 11',
            'gender' => 'Male',
            'email' => 'juan@example.com',
            'sport_id' => Sport::firstOrFail()->id,
            'medical_certificate' => UploadedFile::fake()->create('medical.pdf', 20, 'application/pdf'),
            'birth_certificate' => UploadedFile::fake()->create('birth.pdf', 20, 'application/pdf'),
            'parent_consent' => UploadedFile::fake()->create('consent.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    public function test_complete_application_with_required_documents_is_saved(): void
    {
        $response = $this->post(route('application.store'), $this->applicationPayload());

        $response->assertRedirect(route('application.create'));
        $application = Application::firstOrFail();
        $this->assertDatabaseHas('applications', [
            'name' => 'Juan Dela Cruz', 'student_id' => '2026-0001', 'email' => 'juan@example.com',
            'sport' => 'Basketball', 'status' => 'Pending',
        ]);
        Storage::disk('private')->assertExists($application->medical_certificate_path);
        Storage::disk('private')->assertExists($application->birth_certificate_path);
        Storage::disk('private')->assertExists($application->parent_consent_path);
    }

    public function test_incomplete_or_invalid_application_is_not_saved(): void
    {
        $response = $this->from(route('application.create'))->post(route('application.store'), $this->applicationPayload([
            'email' => 'not-an-email', 'sport_id' => 999, 'parent_consent' => null,
        ]));

        $response->assertRedirect(route('application.create'));
        $response->assertSessionHasErrors(['email', 'sport_id', 'parent_consent']);
        $this->assertSame(0, Application::count());
    }
}
