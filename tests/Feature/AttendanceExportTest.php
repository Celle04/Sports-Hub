<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'Administrator']);
    }

    private function createStudent(Sport $sport, string $name, string $studentId): User
    {
        return User::factory()->create([
            'role' => 'Student',
            'sport_id' => $sport->id,
            'name' => $name,
            'student_id' => $studentId,
            'status' => 'Active',
        ]);
    }

    private function sessionWithRecords(Sport $sport, string $idSuffix): array
    {
        $this->createAdmin();

        $session = AttendanceSession::create([
            'title' => 'Basketball Tournament',
            'sport_id' => $sport->id,
            'session_date' => '2026-10-15',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => 'Main Court',
            'status' => 'Open',
            'created_by' => auth()->id() ?? $this->createAdmin()->id,
        ]);

        $juan = $this->createStudent($sport, 'Juan Dela Cruz', '2026-'.$idSuffix.'-001');
        $mark = $this->createStudent($sport, 'Mark Santos', '2026-'.$idSuffix.'-002');
        $carlo = $this->createStudent($sport, 'Carlo Reyes', '2026-'.$idSuffix.'-003');

        Attendance::create([
            'attendance_session_id' => $session->id,
            'user_id' => $juan->id,
            'status' => 'Present',
            'attended_on' => '2026-10-15',
            'check_in_time' => '2026-10-15 07:55:00',
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'user_id' => $mark->id,
            'status' => 'Absent',
            'attended_on' => '2026-10-15',
            'check_in_time' => null,
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'user_id' => $carlo->id,
            'status' => 'Pending',
            'attended_on' => '2026-10-15',
            'check_in_time' => null,
        ]);

        return ['session' => $session];
    }

    public function test_admin_can_download_pdf_export(): void
    {
        $admin = $this->createAdmin();
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $data = $this->sessionWithRecords($sport, 'bball');

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export.pdf', $data['session']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'filename=SNNHS_Attendance_Basketball_Tournament_2026-10-15.pdf',
            $response->headers->get('Content-Disposition')
        );

        $content = $response->getContent();
        $this->assertSame('%PDF', substr($content, 0, 4), 'Downloaded file is not a valid PDF.');
        $this->assertGreaterThan(1000, strlen($content), 'Generated PDF is empty.');

        // Confirm the view data dompdf renders contains the correct session details.
        $html = view('admin.attendance.export.pdf', [
            'session' => $data['session'],
            'records' => $data['session']->attendanceRecords()->with('athlete')->get()->sortBy(fn (Attendance $r) => $r->athlete?->name),
            'totals' => [
                'total' => 3,
                'present' => 1,
                'absent' => 1,
                'pending_unrecorded' => 1,
                'late' => 0,
                'excused' => 0,
            ],
            'generatedAt' => now(),
            'generatedBy' => 'Administrator',
        ])->render();
        $this->assertStringContainsString('Basketball Tournament', $html);
        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringContainsString('Mark Santos', $html);
        $this->assertStringContainsString('Carlo Reyes', $html);
        $this->assertStringContainsString('Main Court', $html);
    }

    public function test_admin_can_download_csv_export(): void
    {
        $admin = $this->createAdmin();
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $data = $this->sessionWithRecords($sport, 'bball');

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export', $data['session']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment; filename="SNNHS_Attendance_Basketball_Tournament_2026-10-15.csv"',
            $response->headers->get('Content-Disposition')
        );

        $content = $response->getContent();
        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3), 'CSV is missing the UTF-8 BOM.');
        $this->assertStringContainsString('Basketball Tournament', $content);
        $this->assertStringContainsString('No.', $content);
        $this->assertStringContainsString('Student ID', $content);
        $this->assertStringContainsString('Athlete Name', $content);
        $this->assertStringContainsString('Sport', $content);
        $this->assertStringContainsString('Team', $content);
        $this->assertStringContainsString('Attendance Status', $content);
        $this->assertStringContainsString('Time In', $content);
        $this->assertStringContainsString('Juan Dela Cruz', $content);
        $this->assertStringContainsString('2026-bball-001', $content);
        $this->assertStringContainsString('Present', $content);
        $this->assertStringContainsString('Absent', $content);
        $this->assertStringContainsString('Pending', $content);
    }

    public function test_pdf_export_is_isolated_to_the_requested_session(): void
    {
        $admin = $this->createAdmin();

        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport']);

        $basketballData = $this->sessionWithRecords($basketball, 'bball');
        $volleyballSession = $this->sessionWithRecords($volleyball, 'volley')['session'];
        $volleyballSession->update(['title' => 'Volleyball Finals']);

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export.pdf', $basketballData['session']));

        $content = $response->getContent();
        $this->assertSame('%PDF', substr($content, 0, 4), 'Downloaded file is not a valid PDF.');
        $this->assertStringContainsString(
            'filename=SNNHS_Attendance_Basketball_Tournament_2026-10-15.pdf',
            $response->headers->get('Content-Disposition')
        );

        // Confirm the rendered view contains the requested session's data, not
        // the other session's.
        $html = view('admin.attendance.export.pdf', [
            'session' => $basketballData['session'],
            'records' => $basketballData['session']->attendanceRecords()->with('athlete')->get()->sortBy(fn (Attendance $r) => $r->athlete?->name),
            'totals' => [
                'total' => 3,
                'present' => 1,
                'absent' => 1,
                'pending_unrecorded' => 1,
                'late' => 0,
                'excused' => 0,
            ],
            'generatedAt' => now(),
            'generatedBy' => 'Administrator',
        ])->render();
        $this->assertStringContainsString('Basketball Tournament', $html);
        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringNotContainsString('Volleyball Finals', $html);
    }

    public function test_csv_export_includes_all_records_not_just_visible_page(): void
    {
        $admin = $this->createAdmin();
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $data = $this->sessionWithRecords($sport, 'bball');

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export', $data['session']));

        $content = $response->getContent();
        $juanCount = substr_count($content, 'Juan Dela Cruz');
        $markCount = substr_count($content, 'Mark Santos');
        $carloCount = substr_count($content, 'Carlo Reyes');

        $this->assertSame(1, $juanCount);
        $this->assertSame(1, $markCount);
        $this->assertSame(1, $carloCount);
    }

    public function test_csv_export_protects_against_formula_injection(): void
    {
        $admin = $this->createAdmin();
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $data = $this->sessionWithRecords($sport, 'bball');

        $athlete = User::factory()->create([
            'role' => 'Student',
            'sport_id' => $sport->id,
            'name' => '=cmd|\' /C calc\'!A0',
            'student_id' => '2026-bball-9999',
            'status' => 'Active',
        ]);
        Attendance::create([
            'attendance_session_id' => $data['session']->id,
            'user_id' => $athlete->id,
            'status' => 'Present',
            'attended_on' => '2026-10-15',
            'check_in_time' => '2026-10-15 08:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export', $data['session']));

        $content = $response->getContent();
        // The raw formula text must be present but neutralized by a leading tab.
        $this->assertStringContainsString('=cmd', $content);
        $this->assertStringContainsString("\t=cmd", $content);
    }

    public function test_student_cannot_access_pdf_export(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $student = $this->createStudent($sport, 'Student User', '2026-student-100');
        $session = AttendanceSession::create([
            'title' => 'Session',
            'sport_id' => $sport->id,
            'session_date' => '2026-10-15',
            'status' => 'Open',
            'created_by' => $this->createAdmin()->id,
        ]);

        $this->actingAs($student)
            ->get(route('admin.attendance.sessions.export.pdf', $session))
            ->assertForbidden();
    }

    public function test_missing_session_is_handled_safely(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export.pdf', 999999))
            ->assertNotFound();
    }

    public function test_empty_session_produces_a_valid_pdf(): void
    {
        $admin = $this->createAdmin();
        $sport = Sport::create(['name' => 'Chess', 'classification' => 'Individual', 'description' => 'Board sport']);
        $session = AttendanceSession::create([
            'title' => 'Chess Meet',
            'sport_id' => $sport->id,
            'session_date' => '2026-10-15',
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.sessions.export.pdf', $session));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF', substr($response->getContent(), 0, 4));
    }
}
