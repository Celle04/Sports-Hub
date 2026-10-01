<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_create_a_medical_record_for_an_athlete(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Indoor court sport']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'sport_id' => $sport->id]);

        $this->actingAs($admin);

        $this->get(route('admin.medical'))->assertOk();

        $this->post(route('admin.medical.store'), [
            'athlete_id' => $athlete->id,
            'examination_date' => '2026-09-01',
            'medical_status' => 'Cleared',
            'next_checkup_date' => '2027-09-01',
            'findings' => 'Healthy and fit for competition.',
            'restrictions' => 'None',
            'notes' => 'Cleared for school sports activities.',
        ])->assertRedirect();

        $this->assertDatabaseHas('medical_records', [
            'athlete_id' => $athlete->id,
            'medical_status' => 'Cleared',
        ]);
    }
}
