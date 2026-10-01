<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_open_session_and_eligible_student_can_check_in_once(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'name' => 'Juan Athlete']);
        $event = Event::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'venue' => 'SNNHS Gym',
            'starts_at' => now(),
            'ends_at' => now()->addHours(2),
            'status' => 'Scheduled',
        ]);

        $this->actingAs($admin)->post(route('admin.attendance.sessions.store'), [
            'title' => 'Daily Basketball Training',
            'event_id' => $event->id,
            'session_date' => today()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'venue' => 'SNNHS Gymnasium',
            'description' => 'Regular basketball training session.',
        ])->assertSessionHas('success');

        $session = AttendanceSession::firstOrFail();

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)->patch(route('admin.attendance.sessions.open', $session))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($student)->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('Daily Basketball Training')
            ->assertSee('Check In');

        $this->post(route('student.attendance.check-in', $session))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'Present',
        ]);

        $this->post(route('student.attendance.check-in', $session))
            ->assertSessionHasErrors('attendance');

        $this->assertSame(1, Attendance::where('attendance_session_id', $session->id)->where('user_id', $student->id)->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_student_cannot_check_in_to_a_closed_or_wrong_sport_session(): void
    {
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);
        $otherSport = Sport::create(['name' => 'Chess', 'classification' => 'Individual', 'description' => 'Board sport', 'status' => 'Active']);
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $otherSport->id]);
        $session = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Volleyball Training',
            'session_date' => today(),
            'status' => 'Closed',
            'created_by' => User::factory()->create(['role' => 'Administrator'])->id,
        ]);

        $this->actingAs($student)
            ->post(route('student.attendance.check-in', $session))
            ->assertStatus(422);

        $session->update(['status' => 'Open']);

        $this->post(route('student.attendance.check-in', $session))
            ->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }
}
