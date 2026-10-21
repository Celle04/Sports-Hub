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

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_view_attendance_dashboard_and_create_a_session_without_selecting_students(): void
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
        User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-1001']);
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->get(route('admin.attendance'))
            ->assertOk()
            ->assertSee('Total Athletes')
            ->assertSee('Present Today')
            ->assertSee('Attendance Rate')
            ->assertSee('Sessions', false);

        $this->post(route('admin.attendance.sessions.store'), [
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'sport_required' => 1,
            'session_date' => today()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'venue' => 'SNNHS Gymnasium',
            'status' => 'Open',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_sessions', [
            'sport_id' => $sport->id,
            'title' => 'Basketball Training',
            'status' => 'Open',
        ]);

        $this->assertSame(1, Attendance::count());
        $this->assertSame('Pending', Attendance::firstOrFail()->status);

        // The session form exposes no per-athlete selection field.
        $this->actingAs($admin)->get(route('admin.attendance'))
            ->assertOk()
            ->assertDontSee('name="user_id"', false);
    }

    public function test_a_session_can_be_linked_to_an_existing_event_for_its_sport(): void
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
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->post(route('admin.attendance.sessions.store'), [
            'title' => 'Volleyball Training',
            'event_id' => $event->id,
            'session_date' => today()->toDateString(),
            'status' => 'Open',
        ])->assertSessionHas('success');

        $session = AttendanceSession::firstOrFail();

        $this->assertSame($event->id, $session->event_id);
        $this->assertSame($sport->id, $session->sport_id);
        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $athlete->id,
            'status' => 'Pending',
        ]);
    }

    public function test_duplicate_attendance_for_the_same_athlete_and_session_is_prevented(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-1003']);
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->post(route('admin.attendance.sessions.store'), [
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'sport_required' => 1,
            'session_date' => today()->toDateString(),
            'status' => 'Open',
        ]);

        $session = AttendanceSession::firstOrFail();
        $this->assertSame(1, Attendance::where('attendance_session_id', $session->id)->where('user_id', $athlete->id)->count());

        // Re-opening the session re-syncs without creating a second record.
        $this->patch(route('admin.attendance.sessions.open', $session))->assertSessionHas('success');
        $this->assertSame(1, Attendance::where('attendance_session_id', $session->id)->where('user_id', $athlete->id)->count());
    }

    public function test_the_attendance_module_only_shows_attendance_details(): void
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
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('admin.attendance'));

        $response->assertOk()
            ->assertSee('Attendance Sessions')
            // The create form now lives in the Event module.
            ->assertDontSee('Create Attendance Session')
            ->assertDontSee('action="'.route('admin.attendance.sessions.store').'"', false)
            ->assertSee(route('events.index'));
    }

    public function test_the_attendance_module_lists_the_event_of_every_session(): void
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
        $admin = User::factory()->create(['role' => 'Administrator']);

        AttendanceSession::create([
            'title' => 'Volleyball Set 1',
            'event_id' => $event->id,
            'sport_id' => $sport->id,
            'session_date' => today()->toDateString(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin.attendance'))
            ->assertOk()
            ->assertSee('Volleyball Training')
            ->assertSee(route('events.show', $event));
    }

    public function test_the_session_action_forms_carry_their_csrf_token(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball']);
        $admin = User::factory()->create(['role' => 'Administrator']);

        AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today()->toDateString(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get(route('admin.attendance'))->assertOk()->getContent();

        // The token has to live inside the form element, not inside its tag.
        $this->assertStringNotContainsString('sessions/'.AttendanceSession::firstOrFail()->id.'/sync" <input', $html);
        $this->assertMatchesRegularExpression('#<form method="POST" action="[^"]+/sync">\s*<input type="hidden" name="_token"#', $html);
        $this->assertMatchesRegularExpression('#<input type="hidden" name="_method" value="PATCH">#', $html);
    }

    public function test_the_session_list_shows_the_newest_record_first(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball']);
        $admin = User::factory()->create(['role' => 'Administrator']);

        $older = AttendanceSession::create([
            'title' => 'Older Session',
            'sport_id' => $sport->id,
            'session_date' => now()->subWeek()->toDateString(),
            'status' => 'Closed',
            'created_by' => $admin->id,
        ]);

        $newer = AttendanceSession::create([
            'title' => 'Newer Session',
            'sport_id' => $sport->id,
            'session_date' => now()->subWeek()->toDateString(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get(route('admin.attendance'))->assertOk()->getContent();

        // The list follows the upload order, so the latest record leads.
        $this->assertLessThan(
            strpos($html, 'Older Session'),
            strpos($html, 'Newer Session'),
            'The newest attendance session should be listed first.'
        );
    }

    public function test_opening_a_session_jumps_to_its_detail(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball']);
        $admin = User::factory()->create(['role' => 'Administrator']);

        $session = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today()->toDateString(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get(route('admin.attendance'))->assertOk()->getContent();

        // The view link points at the detail card.
        $this->assertStringContainsString('#attendance-session-detail', $html);

        $detail = $this->actingAs($admin)
            ->get(route('admin.attendance', ['session_id' => $session->id]))
            ->assertOk()
            ->getContent();

        // The card carries the id the link scrolls to.
        $this->assertStringContainsString('id="attendance-session-detail"', $detail);
    }
}
