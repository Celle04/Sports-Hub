<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Event;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_database_backed_dashboard_sections(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'School basketball program']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);
        Event::create([
            'title' => 'Basketball Practice',
            'venue' => 'Main Court',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'Scheduled',
            'sport_id' => $sport->id,
        ]);
        Application::create([
            'name' => 'Pending Athlete',
            'email' => 'pending@example.com',
            'student_id' => '2026-9999',
            'grade' => 'Grade 10',
            'gender' => 'Female',
            'sport' => 'Basketball',
            'sport_id' => $sport->id,
            'status' => 'Pending',
        ]);
        MedicalRecord::create([
            'athlete_id' => $athlete->id,
            'user_id' => $athlete->id,
            'examination_date' => today(),
            'medical_status' => 'Cleared',
            'next_checkup_date' => today()->addYear(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Quick Actions');
        $response->assertSee('Upcoming Events');
        $response->assertSee('Basketball Practice');
        $response->assertSee('Pending Sports Applications');
        $response->assertSee('Pending Athlete');
        $response->assertSee('Medical Clearance');
    }
}
