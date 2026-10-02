<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Sport;
use App\Models\User;
use App\Services\AttendanceSessionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SportBasedAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Notification::fake();
    }

    private function sport(string $name): Sport
    {
        return Sport::create(['name' => $name, 'classification' => 'Sport', 'description' => $name, 'status' => 'Active']);
    }

    private function athlete(Sport $sport, string $name, string $studentId): User
    {
        return User::factory()->create([
            'role' => 'Student',
            'sport_id' => $sport->id,
            'name' => $name,
            'student_id' => $studentId,
            'status' => 'Active',
        ]);
    }

    public function test_creating_a_session_assigns_every_athlete_of_that_sport_as_pending(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $athletics = $this->sport('Athletics');

        $juan = $this->athlete($basketball, 'Juan Dela Cruz', '20260001');
        $mark = $this->athlete($basketball, 'Mark Santos', '20260002');
        $carlo = $this->athlete($basketball, 'Carlo Reyes', '20260003');
        $runner = $this->athlete($athletics, 'Pedro Garcia', '20260004');

        $this->actingAs($admin)->post(route('admin.attendance.sessions.store'), [
            'title' => 'Basketball Training',
            'sport_id' => $basketball->id,
            'sport_required' => 1,
            'session_date' => today()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'venue' => 'SNNHS Gymnasium',
            'description' => 'Regular basketball training session',
            'status' => 'Open',
        ])->assertRedirect(route('admin.attendance', ['session_id' => AttendanceSession::firstOrFail()->id]));

        $session = AttendanceSession::firstOrFail();

        $this->assertSame($basketball->id, $session->sport_id);
        $this->assertSame('Open', $session->status);

        foreach ([$juan, $mark, $carlo] as $athlete) {
            $this->assertDatabaseHas('attendances', [
                'attendance_session_id' => $session->id,
                'user_id' => $athlete->id,
                'status' => 'Pending',
            ]);
        }

        $this->assertDatabaseMissing('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $runner->id,
        ]);

        $this->assertSame(3, Attendance::where('attendance_session_id', $session->id)->count());
    }

    public function test_session_requires_a_sport_when_the_admin_demands_one(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)
            ->post(route('admin.attendance.sessions.store'), [
                'title' => 'Orientation',
                'sport_id' => '',
                'sport_required' => 1,
                'session_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('sport_id');
    }

    public function test_athletes_only_see_sessions_for_their_own_assigned_sport(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');
        $badminton = $this->sport('Badminton');

        $juan = $this->athlete($basketball, 'Juan Dela Cruz', '20260001');
        $volleyPlayer = $this->athlete($volleyball, 'Ana Reyes', '20260002');
        $shuttlePlayer = $this->athlete($badminton, 'Badminton Player', '20260003');

        foreach ([['Basketball Training', $basketball], ['Volleyball Training', $volleyball], ['Badminton Practice', $badminton]] as [$title, $sport]) {
            AttendanceSession::create([
                'title' => $title,
                'sport_id' => $sport->id,
                'session_date' => today(),
                'status' => 'Open',
                'created_by' => $admin->id,
            ]);
        }

        $this->actingAs($juan)->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertDontSee('Volleyball Training')
            ->assertDontSee('Badminton Practice');

        $this->actingAs($volleyPlayer)->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('Volleyball Training')
            ->assertDontSee('Basketball Training');

        $this->actingAs($shuttlePlayer)->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('Badminton Practice')
            ->assertDontSee('Basketball Training');
    }

    public function test_athlete_can_check_in_once_and_time_is_recorded_automatically(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $juan = $this->athlete($sport, 'Juan Dela Cruz', '20260001');
        $mark = $this->athlete($sport, 'Mark Santos', '20260002');

        $session = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'start_time' => now()->subHour()->format('H:i'),
            'late_grace_minutes' => 120,
            'venue' => 'SNNHS Gymnasium',
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        app(AttendanceSessionService::class)->syncRoster($session);

        $this->actingAs($juan)->post(route('student.attendance.check-in', $session))
            ->assertSessionHas('success');

        $record = Attendance::where('attendance_session_id', $session->id)->where('user_id', $juan->id)->firstOrFail();

        $this->assertSame('Present', $record->status);
        $this->assertNotNull($record->check_in_time);

        $this->actingAs($juan)->post(route('student.attendance.check-in', $session))
            ->assertSessionHasErrors('attendance');

        $this->assertSame(1, Attendance::where('attendance_session_id', $session->id)->where('user_id', $juan->id)->count());
        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $mark->id,
            'status' => 'Pending',
        ]);
    }

    public function test_check_in_beyond_the_grace_period_is_marked_late(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Track and Field');
        $runner = $this->athlete($sport, 'Runner Boy', '20260005');

        $session = AttendanceSession::create([
            'title' => 'Athletics Training',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'start_time' => now()->subHour()->format('H:i'),
            'late_grace_minutes' => 5,
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        app(AttendanceSessionService::class)->syncRoster($session);

        $this->actingAs($runner)->post(route('student.attendance.check-in', $session));

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $runner->id,
            'status' => 'Late',
        ]);
    }

    public function test_athlete_cannot_check_in_for_another_sport_or_a_closed_session(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $athletics = $this->sport('Athletics');
        $runner = $this->athlete($athletics, 'Runner Boy', '20260005');

        $closed = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $basketball->id,
            'session_date' => today(),
            'status' => 'Closed',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($runner)->post(route('student.attendance.check-in', $closed))->assertStatus(422);

        $open = AttendanceSession::create([
            'title' => 'Basketball Training Open',
            'sport_id' => $basketball->id,
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($runner)->post(route('student.attendance.check-in', $open))->assertForbidden();
        $this->assertDatabaseMissing('attendances', ['attendance_session_id' => $open->id, 'user_id' => $runner->id]);
    }

    public function test_closed_and_cancelled_sessions_reject_new_check_ins(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $juan = $this->athlete($sport, 'Juan Dela Cruz', '20260001');

        $cancelled = AttendanceSession::create([
            'title' => 'Cancelled Session',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.attendance.sessions.cancel', $cancelled))->assertSessionHas('success');
        $this->assertSame('Cancelled', $cancelled->fresh()->status);

        $this->actingAs($juan)->post(route('student.attendance.check-in', $cancelled))->assertStatus(422);

        $closed = AttendanceSession::create([
            'title' => 'Closed Session',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.attendance.sessions.close', $closed))->assertSessionHas('success');

        $this->actingAs($juan)->post(route('student.attendance.check-in', $closed))->assertStatus(422);
    }

    public function test_repeated_synchronisation_never_duplicates_records(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $this->athlete($sport, 'Juan Dela Cruz', '20260001');

        $session = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $service = app(AttendanceSessionService::class);

        $this->assertSame(1, $service->syncRoster($session));
        $this->assertSame(0, $service->syncRoster($session));
        $this->assertSame(0, $service->syncRoster($session->fresh()));
        $this->assertSame(1, Attendance::where('attendance_session_id', $session->id)->count());
    }

    public function test_roster_follows_a_sport_reassignment_without_touching_real_attendance(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $athletics = $this->sport('Athletics');
        $juan = $this->athlete($basketball, 'Juan Dela Cruz', '20260001');

        $session = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $basketball->id,
            'session_date' => today(),
            'status' => 'Closed',
            'created_by' => $admin->id,
        ]);

        app(AttendanceSessionService::class)->syncRoster($session);
        Attendance::where('attendance_session_id', $session->id)->update(['status' => 'Present', 'check_in_time' => now()]);

        $juan->update(['sport_id' => $athletics->id]);

        $this->actingAs($admin)->patch(route('admin.attendance.sessions.sync', $session))->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'user_id' => $juan->id,
            'status' => 'Present',
        ]);

        // The session no longer belongs to Juan's sport, so it is not offered to him again.
        $this->assertFalse($juan->attendanceSessions()->whereKey($session->id)->exists());

        $this->actingAs($juan)->get(route('student.attendance'))
            ->assertOk()
            ->assertDontSee('Check In');

        // A new session for the new sport reaches him instead.
        $athleticsSession = AttendanceSession::create([
            'title' => 'Athletics Training',
            'sport_id' => $athletics->id,
            'session_date' => today()->addDay(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($juan)->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('Athletics Training');
    }

    public function test_admin_filters_sessions_by_sport_date_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');

        $today = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $basketball->id,
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        $other = AttendanceSession::create([
            'title' => 'Volleyball Practice',
            'sport_id' => $volleyball->id,
            'session_date' => today()->addDay(),
            'status' => 'Closed',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin.attendance', ['sport_id' => $basketball->id]))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertDontSee('Volleyball Practice');

        $this->actingAs($admin)->get(route('admin.attendance', ['status' => 'Closed']))
            ->assertOk()
            ->assertSee('Volleyball Practice')
            ->assertDontSee('Basketball Training');

        $this->actingAs($admin)->get(route('admin.attendance', ['date' => today()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('Volleyball Practice')
            ->assertDontSee('Basketball Training');

        $this->actingAs($admin)->get(route('admin.attendance', ['search' => 'Basketball']))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertDontSee('Volleyball Practice');

        $this->assertNotSame($today->id, $other->id);
    }

    public function test_admin_sees_the_auto_generated_roster_and_can_correct_a_status(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $juan = $this->athlete($sport, 'Juan Dela Cruz', '20260001');
        $mark = $this->athlete($sport, 'Mark Santos', '20260002');

        $session = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'venue' => 'SNNHS Gymnasium',
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        app(AttendanceSessionService::class)->syncRoster($session);

        $this->actingAs($admin)->get(route('admin.attendance', ['session_id' => $session->id]))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('20260001')
            ->assertSee('Mark Santos')
            ->assertSee('SNNHS Gymnasium')
            ->assertSee('Close Attendance');

        $pending = Attendance::where('attendance_session_id', $session->id)->where('user_id', $mark->id)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.attendance.records.update', $pending), [
            'status' => 'Absent',
        ])->assertRedirect();

        $this->assertSame('Absent', $pending->fresh()->status);

        $other = Attendance::where('attendance_session_id', $session->id)->where('user_id', $juan->id)->firstOrFail();
        $this->assertSame('Pending', $other->fresh()->status);
    }

    public function test_only_administrators_reach_attendance_management(): void
    {
        $athlete = User::factory()->create(['role' => 'Student']);

        $this->actingAs($athlete)->get(route('admin.attendance'))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.attendance.sessions.store'), [
            'title' => 'Nope',
            'session_date' => today()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($athlete)->get(route('reports.index'))->assertForbidden();
    }

    public function test_session_report_can_be_exported_as_csv(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $this->athlete($sport, 'Juan Dela Cruz', '20260001');

        $session = AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'venue' => 'SNNHS Gymnasium',
            'status' => 'Closed',
            'created_by' => $admin->id,
        ]);

        app(AttendanceSessionService::class)->syncRoster($session);

        $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export', $session))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_attendance_rate_ignores_pending_and_cancelled_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $juan = $this->athlete($sport, 'Juan Dela Cruz', '20260001');

        foreach (['Present', 'Absent'] as $index => $status) {
            $session = AttendanceSession::create([
                'title' => 'Session '.$index,
                'sport_id' => $sport->id,
                'session_date' => today()->subDays($index + 1),
                'status' => 'Closed',
                'created_by' => $admin->id,
            ]);

            Attendance::create([
                'attendance_session_id' => $session->id,
                'user_id' => $juan->id,
                'status' => $status,
                'attended_on' => $session->session_date,
            ]);
        }

        $cancelled = AttendanceSession::create([
            'title' => 'Cancelled Session',
            'sport_id' => $sport->id,
            'session_date' => today()->subDays(5),
            'status' => 'Cancelled',
            'created_by' => $admin->id,
        ]);

        Attendance::create([
            'attendance_session_id' => $cancelled->id,
            'user_id' => $juan->id,
            'status' => 'Absent',
            'attended_on' => $cancelled->session_date,
        ]);

        $pendingSession = AttendanceSession::create([
            'title' => 'Pending Session',
            'sport_id' => $sport->id,
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        app(AttendanceSessionService::class)->syncRoster($pendingSession);

        $this->assertSame(50.0, app(AttendanceSessionService::class)->rateFor($juan));

        $this->actingAs($juan)->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('50%');
    }

    public function test_all_sports_session_reaches_every_active_athlete(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $chess = $this->sport('Chess');

        $juan = $this->athlete($basketball, 'Juan Dela Cruz', '20260001');
        $player = $this->athlete($chess, 'Chess Player', '20260002');
        $inactive = $this->athlete($chess, 'Retired Player', '20260003');
        $inactive->update(['status' => 'Inactive']);

        $this->actingAs($admin)->post(route('admin.attendance.sessions.store'), [
            'title' => 'School Sports Orientation',
            'sport_id' => '',
            'session_date' => today()->addDay()->toDateString(),
            'status' => 'Open',
        ])->assertSessionHas('success');

        $session = AttendanceSession::firstOrFail();

        $this->assertNull($session->sport_id);
        $this->assertDatabaseHas('attendances', ['attendance_session_id' => $session->id, 'user_id' => $juan->id]);
        $this->assertDatabaseHas('attendances', ['attendance_session_id' => $session->id, 'user_id' => $player->id]);
        $this->assertDatabaseMissing('attendances', ['attendance_session_id' => $session->id, 'user_id' => $inactive->id]);

        $this->actingAs($juan)->get(route('student.attendance'))->assertOk()->assertSee('School Sports Orientation');
        $this->actingAs($player)->get(route('student.attendance'))->assertOk()->assertSee('School Sports Orientation');
    }

    public function test_athlete_without_a_sport_sees_no_attendance_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport('Basketball');
        $this->athlete($sport, 'Juan Dela Cruz', '20260001');

        $unassigned = User::factory()->create(['role' => 'Student', 'sport_id' => null, 'student_id' => '20260009']);

        AttendanceSession::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'session_date' => today()->addDay(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($unassigned)->get(route('student.attendance'))
            ->assertOk()
            ->assertDontSee('Basketball Training');
    }
}
