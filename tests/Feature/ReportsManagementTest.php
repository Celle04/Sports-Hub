<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Administrator']);
    }

    private function sport(string $name = 'Basketball'): Sport
    {
        return Sport::create(['name' => $name, 'classification' => 'Team Sport', 'description' => $name.' sport']);
    }

    public function test_only_administrators_can_view_or_export_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.export', ['format' => 'csv']))->assertRedirect(route('login'));

        $student = User::factory()->create(['role' => 'Student']);

        $this->actingAs($student)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($student)
            ->get(route('reports.export', ['format' => 'csv']))
            ->assertForbidden();

        $this->actingAs($this->admin())->get(route('reports.index'))->assertOk();
    }

    public function test_dashboard_shows_overview_stats_and_report_picker(): void
    {
        $sport = $this->sport();
        Coach::create(['name' => 'Coach Santos', 'email' => 'santos@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id, 'coach_type' => 'Head Coach', 'status' => 'Active']);

        $this->actingAs($this->admin())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reporting Center')
            ->assertSee('Athlete List')
            ->assertSee('Attendance Records')
            ->assertSee('Athlete Attendance Summary')
            ->assertSee('Coach List')
            ->assertSee('Total Athletes')
            ->assertSee('Total Coaches')
            ->assertSee('Total Sports')
            ->assertSee('Attendance Rate');
    }

    public function test_attendance_report_shows_every_record_summary_and_rate(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Athletics');
        $session = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Morning Training',
            'session_date' => today(),
            'status' => 'Open',
            'start_time' => '07:00',
            'created_by' => $admin->id,
        ]);
        $cancelled = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Rained Out',
            'session_date' => today(),
            'status' => 'Cancelled',
            'created_by' => $admin->id,
        ]);

        $first = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'name' => 'Report Athlete A']);
        $second = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'name' => 'Report Athlete B']);
        $third = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'name' => 'Report Athlete C']);

        Attendance::create(['attendance_session_id' => $session->id, 'user_id' => $first->id, 'status' => 'Present', 'attended_on' => today()->toDateString(), 'check_in_time' => now()]);
        Attendance::create(['attendance_session_id' => $session->id, 'user_id' => $second->id, 'status' => 'Late', 'attended_on' => today()->toDateString()]);
        Attendance::create(['attendance_session_id' => $session->id, 'user_id' => $third->id, 'status' => 'Pending', 'attended_on' => today()->toDateString()]);
        Attendance::create(['attendance_session_id' => $cancelled->id, 'user_id' => $first->id, 'status' => 'Absent', 'attended_on' => today()->toDateString()]);

        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'attendance']))
            ->assertOk()
            ->assertSee('ATTENDANCE REPORT')
            ->assertSee('Morning Training')
            ->assertSee('Report Athlete A')
            ->assertSee('Report Athlete B')
            ->assertSee('Report Athlete C')
            ->assertSee('Total Records')
            ->assertSee('Pending (not counted)')
            ->assertSee('100%')
            ->assertSee('7:00 AM');

        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'attendance', 'sport_id' => $sport->id, 'status' => 'Pending']))
            ->assertOk()
            ->assertSee('Report Athlete C')
            ->assertSee('1 record(s)')
            ->assertSee('Filters: Sport: Athletics · Status: Pending');
    }

    public function test_attendance_report_summary_excludes_cancelled_sessions_and_pending_rows(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Swimming');
        $presentSession = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Lane Work',
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        $absentSession = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Endurance Set',
            'session_date' => today()->subDay(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        $pendingSession = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Time Trial',
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);

        Attendance::create(['attendance_session_id' => $presentSession->id, 'user_id' => $athlete->id, 'status' => 'Present', 'attended_on' => today()->toDateString()]);
        Attendance::create(['attendance_session_id' => $absentSession->id, 'user_id' => $athlete->id, 'status' => 'Absent', 'attended_on' => today()->subDay()->toDateString()]);
        Attendance::create(['attendance_session_id' => $pendingSession->id, 'user_id' => $athlete->id, 'status' => 'Pending', 'attended_on' => today()->toDateString()]);

        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'attendance', 'sport_id' => $sport->id]))
            ->assertOk()
            ->assertSee('50%');
    }

    public function test_athlete_report_lists_the_complete_roster_and_applies_shared_filters(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Basketball');
        $other = $this->sport('Volleyball');

        $listed = User::factory()->create(['role' => 'Student', 'name' => 'Roster Jane', 'sport_id' => $sport->id, 'student_id' => '2026-7001', 'status' => 'Active']);
        $hidden = User::factory()->create(['role' => 'Student', 'name' => 'Roster John', 'sport_id' => $other->id, 'student_id' => '2026-7002', 'status' => 'Active']);
        User::factory()->create(['role' => 'Administrator', 'name' => 'Roster Admin']);

        Application::create([
            'name' => 'Roster Jane',
            'athlete_id' => $listed->id,
            'student_id' => '2026-7001',
            'grade' => 'Grade 10',
            'gender' => 'Female',
            'email' => 'jane@example.com',
            'sport' => 'Basketball',
            'sport_id' => $sport->id,
            'status' => 'Pending',
        ]);
        Coach::create(['name' => 'Coach Dela Cruz', 'email' => 'cruz@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id, 'coach_type' => 'Head Coach', 'status' => 'Active']);

        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'athletes']))
            ->assertOk()
            ->assertSee('OFFICIAL ATHLETE LIST')
            ->assertSee('Roster Jane')
            ->assertSee('Roster John')
            ->assertSee('Coach Dela Cruz')
            ->assertDontSee('Roster Admin');

        $this->get(route('reports.index', ['report' => 'athletes', 'search' => 'Roster Jane']))
            ->assertOk()
            ->assertSee('Roster Jane')
            ->assertDontSee('Roster John');

        $this->get(route('reports.index', ['report' => 'athletes', 'sport_id' => $sport->id]))
            ->assertOk()
            ->assertSee('Roster Jane')
            ->assertDontSee('Roster John');

        $this->get(route('reports.index', ['report' => 'athletes', 'grade' => 'Grade 10', 'gender' => 'Female']))
            ->assertOk()
            ->assertSee('Roster Jane')
            ->assertDontSee('Roster John');
    }

    public function test_athlete_attendance_summary_report_shows_rates_and_handles_zero_records(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Archery');
        $rangeSession = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Range Session',
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        $targetSession = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Target Practice',
            'session_date' => today()->subDay(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        $pendingSession = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Scoring Run',
            'session_date' => today(),
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $scored = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'name' => 'Summary Scored']);
        $rookie = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'name' => 'Summary Rookie']);

        Attendance::create(['attendance_session_id' => $rangeSession->id, 'user_id' => $scored->id, 'status' => 'Present', 'attended_on' => today()->toDateString()]);
        Attendance::create(['attendance_session_id' => $targetSession->id, 'user_id' => $scored->id, 'status' => 'Absent', 'attended_on' => today()->subDay()->toDateString()]);
        Attendance::create(['attendance_session_id' => $pendingSession->id, 'user_id' => $scored->id, 'status' => 'Pending', 'attended_on' => today()->toDateString()]);

        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'athlete-summary', 'sport_id' => $sport->id]))
            ->assertOk()
            ->assertSee('ATHLETE ATTENDANCE SUMMARY')
            ->assertSee('Summary Scored')
            ->assertSee('Summary Rookie')
            ->assertSee('50%')
            ->assertSee('0%');
    }

    public function test_coach_report_lists_every_coach_with_sport_and_type(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Badminton');

        Coach::create(['name' => 'Coach Reyes', 'email' => 'reyes@example.com', 'specialty' => 'Smash Play', 'sport_id' => $sport->id, 'coach_type' => 'Head Coach', 'status' => 'Active']);
        Coach::create(['name' => 'Coach Ramos', 'email' => 'ramos@example.com', 'specialty' => 'Footwork', 'sport_id' => $sport->id, 'coach_type' => 'Assistant Coach', 'status' => 'Inactive']);

        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'coaches']))
            ->assertOk()
            ->assertSee('OFFICIAL COACH LIST')
            ->assertSee('Coach Reyes')
            ->assertSee('Coach Ramos')
            ->assertSee('Badminton')
            ->assertSee('Head Coach')
            ->assertSee('Assistant Coach');

        $this->get(route('reports.index', ['report' => 'coaches', 'coach_type' => 'Head Coach']))
            ->assertOk()
            ->assertSee('Coach Reyes')
            ->assertDontSee('Coach Ramos');

        $this->get(route('reports.index', ['report' => 'coaches', 'search' => 'Ramos']))
            ->assertOk()
            ->assertSee('Coach Ramos')
            ->assertDontSee('Coach Reyes');
    }

    public function test_report_filters_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index', ['report' => 'attendance', 'status' => 'Unknown']))
            ->assertSessionHasErrors('status');

        $this->get(route('reports.index', ['report' => 'athletes', 'sport_id' => 999999]))
            ->assertSessionHasErrors('sport_id');

        $this->get(route('reports.index', ['report' => 'attendance', 'from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('to');

        $this->get(route('reports.export', ['format' => 'zip']))
            ->assertSessionHasErrors('format');
    }

    public function test_legacy_report_type_parameter_still_works(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index', ['report_type' => 'attendance']))
            ->assertOk()
            ->assertSee('ATTENDANCE REPORT');

        $this->get(route('reports.index', ['report_type' => 'sports']))
            ->assertOk()
            ->assertSee('Reporting Center');
    }

    public function test_empty_reports_show_their_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index', ['report' => 'attendance']))
            ->assertOk()
            ->assertSee('No attendance records found for the selected criteria.');

        $this->get(route('reports.index', ['report' => 'athletes']))
            ->assertOk()
            ->assertSee('No athletes found.');
    }

    public function test_attendance_report_can_be_exported_as_csv(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Volleyball');
        $athlete = User::factory()->create(['name' => 'Export Athlete', 'role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-5003']);
        $event = Event::create([
            'title' => 'Export Training',
            'sport_id' => $sport->id,
            'venue' => 'Gym',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => 'Scheduled',
        ]);
        Attendance::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'Late', 'attended_on' => today()->toDateString()]);

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'attendance', 'sport_id' => $sport->id]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('SURIGAO DEL NORTE NATIONAL HIGH SCHOOL', $csv);
        $this->assertStringContainsString('ATTENDANCE REPORT', $csv);
        $this->assertStringContainsString('Export Athlete', $csv);
        $this->assertStringContainsString('Export Training', $csv);
        $this->assertStringContainsString('Late', $csv);

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('.csv', $disposition);
        $this->assertStringContainsString('attendance-report-volleyball-', $disposition);
    }

    public function test_athlete_report_can_be_exported_as_csv_with_summary(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Fencing');
        User::factory()->create(['name' => 'Roster Exporter', 'role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-8001']);

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'athletes']))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('OFFICIAL ATHLETE LIST', $csv);
        $this->assertStringContainsString('Total Athletes', $csv);
        $this->assertStringContainsString('Roster Exporter', $csv);
        $this->assertStringContainsString('Student ID', $csv);
    }

    public function test_the_athlete_list_can_be_categorized_by_sport_boys_and_girls(): void
    {
        $admin = $this->admin();
        $boysSport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball', 'gender' => 'Boys']);
        $girlsSport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Volleyball', 'gender' => 'Girls']);

        $boy = User::factory()->create(['role' => 'Student', 'name' => 'Category Boy', 'sport_id' => $boysSport->id, 'student_id' => '2026-9101']);
        $girl = User::factory()->create(['role' => 'Student', 'name' => 'Category Girl', 'sport_id' => $girlsSport->id, 'student_id' => '2026-9102']);

        Application::create(['name' => 'Category Boy', 'athlete_id' => $boy->id, 'student_id' => '2026-9101', 'grade' => 'Grade 10', 'gender' => 'Male', 'email' => 'boy@example.com', 'sport' => 'Basketball', 'sport_id' => $boysSport->id, 'status' => 'Pending']);
        Application::create(['name' => 'Category Girl', 'athlete_id' => $girl->id, 'student_id' => '2026-9102', 'grade' => 'Grade 10', 'gender' => 'Female', 'email' => 'girl@example.com', 'sport' => 'Volleyball', 'sport_id' => $girlsSport->id, 'status' => 'Pending']);

        // All athletes.
        $this->actingAs($admin)->get(route('reports.index', ['report' => 'athletes']))
            ->assertOk()
            ->assertSee('Category Boy')
            ->assertSee('Category Girl');

        // Boys only.
        $this->actingAs($admin)->get(route('reports.index', ['report' => 'athletes', 'category' => 'boys']))
            ->assertOk()
            ->assertSee('Category Boy')
            ->assertDontSee('Category Girl');

        // Girls only.
        $this->actingAs($admin)->get(route('reports.index', ['report' => 'athletes', 'category' => 'girls']))
            ->assertOk()
            ->assertSee('Category Girl')
            ->assertDontSee('Category Boy');

        // By sport: every athlete is filed under the sport they play.
        $this->actingAs($admin)->get(route('reports.index', ['report' => 'athletes', 'category' => 'sport']))
            ->assertOk()
            ->assertSee('Basketball')
            ->assertSee('Volleyball');
    }

    public function test_the_athlete_list_download_follows_the_chosen_category(): void
    {
        $admin = $this->admin();
        $boysSport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball', 'gender' => 'Boys']);
        $girlsSport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Volleyball', 'gender' => 'Girls']);

        $boy = User::factory()->create(['role' => 'Student', 'name' => 'Download Boy', 'sport_id' => $boysSport->id, 'student_id' => '2026-9201']);
        $girl = User::factory()->create(['role' => 'Student', 'name' => 'Download Girl', 'sport_id' => $girlsSport->id, 'student_id' => '2026-9202']);

        Application::create(['name' => 'Download Boy', 'athlete_id' => $boy->id, 'student_id' => '2026-9201', 'grade' => 'Grade 10', 'gender' => 'Male', 'email' => 'boy@example.com', 'sport' => 'Basketball', 'sport_id' => $boysSport->id, 'status' => 'Pending']);
        Application::create(['name' => 'Download Girl', 'athlete_id' => $girl->id, 'student_id' => '2026-9202', 'grade' => 'Grade 10', 'gender' => 'Female', 'email' => 'girl@example.com', 'sport' => 'Volleyball', 'sport_id' => $girlsSport->id, 'status' => 'Pending']);

        // The boys download only carries the boys.
        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'athletes', 'category' => 'boys']))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Download Boy', $csv);
        $this->assertStringNotContainsString('Download Girl', $csv);
        $this->assertStringContainsString('Category: Boys', $csv);
        $this->assertStringContainsString('boys', $response->headers->get('content-disposition'));

        // The by-sport download repeats a heading for every sport.
        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'athletes', 'category' => 'sport']))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('BASKETBALL', $csv);
        $this->assertStringContainsString('VOLLEYBALL', $csv);
        $this->assertStringContainsString('Download Boy', $csv);
        $this->assertStringContainsString('Download Girl', $csv);
    }

    public function test_the_attendance_summary_can_be_downloaded_for_a_single_event(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Sepak Takraw');
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Event Athlete', 'sport_id' => $sport->id, 'student_id' => '2026-9301']);

        $wanted = Event::create(['title' => 'Wanted Event', 'sport_id' => $sport->id, 'venue' => 'Gym', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'status' => 'Scheduled']);
        $other = Event::create(['title' => 'Other Event', 'sport_id' => $sport->id, 'venue' => 'Gym', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'status' => 'Scheduled']);

        Attendance::create(['event_id' => $wanted->id, 'user_id' => $athlete->id, 'status' => 'Present', 'attended_on' => today()->toDateString()]);
        Attendance::create(['event_id' => $other->id, 'user_id' => $athlete->id, 'status' => 'Absent', 'attended_on' => today()->toDateString()]);

        // The preview is filtered by the chosen event.
        $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'attendance', 'event_id' => $wanted->id]))
            ->assertOk()
            ->assertSee('Wanted Event')
            ->assertSee('Event: Wanted Event');

        // The download follows the same event.
        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'attendance', 'event_id' => $wanted->id]))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Wanted Event', $csv);
        $this->assertStringNotContainsString('Other Event', $csv);
        $this->assertStringContainsString('Event: Wanted Event', $csv);
        $this->assertStringContainsString('wanted-event', $response->headers->get('content-disposition'));
    }

    public function test_the_athlete_list_export_scope_chooses_all_athletes_or_by_sport(): void
    {
        $admin = $this->admin();
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');
        User::factory()->create(['name' => 'Scope Player One', 'role' => 'Student', 'sport_id' => $basketball->id]);
        User::factory()->create(['name' => 'Scope Player Two', 'role' => 'Student', 'sport_id' => $volleyball->id]);

        // The export panel offers both scopes.
        $this->actingAs($admin)->get(route('reports.index', ['report' => 'athletes']))
            ->assertOk()
            ->assertSee('All athletes')
            ->assertSee('By sport')
            ->assertSee('export_scope=sport', false);

        // "All athletes" keeps every athlete in one list.
        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'athletes', 'export_scope' => 'all']))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Scope Player One', $csv);
        $this->assertStringContainsString('Scope Player Two', $csv);
        $this->assertStringNotContainsString('BASKETBALL', $csv);

        // "By sport" files the list under a heading for every sport.
        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'athletes', 'export_scope' => 'sport']))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('BASKETBALL', $csv);
        $this->assertStringContainsString('VOLLEYBALL', $csv);
        $this->assertStringContainsString('Scope Player One', $csv);
        $this->assertStringContainsString('Scope Player Two', $csv);
    }

    public function test_the_athlete_list_export_scope_is_validated(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index', ['report' => 'athletes', 'export_scope' => 'unknown']))
            ->assertSessionHasErrors('export_scope');
    }

    public function test_reports_never_expose_passwords_or_remember_tokens(): void
    {        $admin = $this->admin();
        $sport = $this->sport('Sepak Takraw');
        $athlete = User::factory()->create(['name' => 'Private Athlete', 'role' => 'Student', 'sport_id' => $sport->id]);

        $page = $this->actingAs($admin)
            ->get(route('reports.index', ['report' => 'athletes']))
            ->assertOk()
            ->assertDontSee($athlete->password)
            ->assertDontSee('remember_token');

        $csv = $this->get(route('reports.export', ['format' => 'csv', 'report' => 'athletes']))->streamedContent();

        $this->assertStringNotContainsString($athlete->password, $csv);
        $this->assertStringNotContainsString('remember_token', $csv);
        $this->assertStringNotContainsString('password', $csv);
    }

    public function test_report_exports_are_available_as_pdf(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Table Tennis');
        User::factory()->create(['name' => 'PDF Athlete', 'role' => 'Student', 'sport_id' => $sport->id]);

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'pdf', 'report' => 'athletes']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('.pdf', $disposition);
        $this->assertStringContainsString('athlete-list-', $disposition);
    }

    public function test_every_report_can_be_previewed_and_printed(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Chess');
        User::factory()->create(['name' => 'Print Athlete', 'role' => 'Student', 'sport_id' => $sport->id]);
        Coach::create(['name' => 'Coach Print', 'email' => 'print@example.com', 'specialty' => 'Endgame', 'sport_id' => $sport->id, 'coach_type' => 'Head Coach', 'status' => 'Active']);

        foreach (['athletes', 'attendance', 'coaches', 'athlete-summary'] as $report) {
            $this->actingAs($admin)
                ->get(route('reports.index', ['report' => $report]))
                ->assertOk()
                ->assertSee('Export CSV')
                ->assertSee('Export PDF')
                ->assertSee('Print');
        }

        $this->get(route('reports.index', ['report' => 'coaches']))
            ->assertOk()
            ->assertSee('Coach Print')
            ->assertSee('Chess');
    }
}
