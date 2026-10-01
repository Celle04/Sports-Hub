<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Event;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AthleteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_filter_and_view_an_athlete_profile(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        Coach::create(['name' => 'Coach Santos', 'email' => 'coach@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id]);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'student_id' => '2026-0001', 'sport_id' => $sport->id]);
        $application = Application::create(['name' => $athlete->name, 'student_id' => $athlete->student_id, 'grade' => 'Grade 10', 'gender' => 'Male', 'email' => $athlete->email, 'sport' => $sport->name, 'sport_id' => $sport->id, 'athlete_id' => $athlete->id, 'status' => 'Approved']);
        MedicalRecord::create(['user_id' => $athlete->id, 'athlete_id' => $athlete->id, 'medical_status' => 'Cleared', 'examination_date' => today()]);
        $event = Event::create(['title' => 'Basketball Practice', 'sport_id' => $sport->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'venue' => 'Main Court', 'status' => 'Scheduled']);
        Attendance::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'Present', 'attended_on' => today()]);

        $this->actingAs($admin)->get(route('athletes.index', ['search' => 'Juan', 'eligibility' => 'Eligible']))
            ->assertOk()
            ->assertSee('Juan Dela Cruz');
        $this->actingAs($admin)->get(route('athletes.show', $athlete))
            ->assertOk()
            ->assertSee('Basketball Practice')
            ->assertSee('Coach Santos')
            ->assertSee('Eligible');
    }

    public function test_related_athlete_is_deactivated_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);
        Application::create(['name' => $athlete->name, 'student_id' => $athlete->student_id, 'grade' => 'Grade 10', 'gender' => 'Male', 'email' => $athlete->email, 'sport' => 'General', 'athlete_id' => $athlete->id, 'status' => 'Pending']);

        $this->actingAs($admin)->delete(route('athletes.destroy', $athlete));

        $this->assertDatabaseHas('users', ['id' => $athlete->id, 'status' => 'Inactive']);
    }
}
