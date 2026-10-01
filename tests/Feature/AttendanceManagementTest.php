<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_view_attendance_dashboard_and_record_attendance(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball']);
        $event = Event::create([
            'title' => 'Practice Session',
            'sport_id' => $sport->id,
            'venue' => 'Main Court',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => 'Scheduled',
        ]);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-1001']);
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $this->get(route('admin.attendance'))
            ->assertOk()
            ->assertSee('Total Athletes')
            ->assertSee('Present Today')
            ->assertSee('Attendance Rate');

        $this->post(route('admin.attendance.store'), [
            'attended_on' => today()->toDateString(),
            'sport_id' => $sport->id,
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'status' => 'Present',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'status' => 'Present',
        ]);
    }

    public function test_duplicate_attendance_for_same_athlete_event_and_date_is_rejected(): void
    {
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Volleyball']);
        $event = Event::create([
            'title' => 'Volleyball Training',
            'sport_id' => $sport->id,
            'venue' => 'Gym',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => 'Scheduled',
        ]);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-1002']);
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        Attendance::create([
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'status' => 'Present',
            'attended_on' => today()->toDateString(),
            'remarks' => 'Recorded',
        ]);

        $response = $this->from(route('admin.attendance'))->post(route('admin.attendance.store'), [
            'attended_on' => today()->toDateString(),
            'sport_id' => $sport->id,
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'status' => 'Late',
        ]);

        $response->assertSessionHasErrors('user_id');
    }
}
