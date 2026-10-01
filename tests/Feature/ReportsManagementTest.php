<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ReportsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_only_administrators_can_view_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'Student']))
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_reports_use_real_data_filters_and_report_types(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-5001']);
        $event = Event::create([
            'title' => 'Report Practice',
            'sport_id' => $sport->id,
            'venue' => 'Main Court',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHour(),
            'status' => 'Completed',
        ]);
        Attendance::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'Present', 'attended_on' => today()->toDateString()]);
        Application::create([
            'name' => 'Report Applicant',
            'student_id' => '2026-5002',
            'grade' => 'Grade 10',
            'email' => 'report@example.com',
            'sport' => 'Basketball',
            'sport_id' => $sport->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($admin);

        $this->get(route('reports.index', ['sport_id' => $sport->id, 'report_type' => 'attendance']))
            ->assertOk()
            ->assertSee('Attendance Records')
            ->assertSee('Report Practice')
            ->assertSee('100%');

        foreach (['athletes', 'sports', 'events', 'applications'] as $type) {
            $this->get(route('reports.index', ['report_type' => $type]))
                ->assertOk();
        }
    }

    public function test_filtered_attendance_can_be_exported_as_csv(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport']);
        $athlete = User::factory()->create(['name' => 'Report Athlete', 'role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-5003']);
        $event = Event::create([
            'title' => 'Export Training',
            'sport_id' => $sport->id,
            'venue' => 'Gym',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => 'Scheduled',
        ]);
        Attendance::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'Late', 'attended_on' => today()->toDateString()]);

        $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'sport_id' => $sport->id]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
