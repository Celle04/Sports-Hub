<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use App\Notifications\AttendanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_current_admin_managed_sports_events_and_application(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-7001']);
        Event::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'venue' => 'SNNHS Gym',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'Scheduled',
        ]);
        Event::create([
            'title' => 'Cancelled Training',
            'sport_id' => $sport->id,
            'venue' => 'Old Gym',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'Cancelled',
        ]);
        Application::create([
            'name' => $athlete->name,
            'student_id' => $athlete->student_id,
            'grade' => 'Grade 10',
            'gender' => 'Female',
            'email' => $athlete->email,
            'sport' => $sport->name,
            'sport_id' => $sport->id,
            'status' => 'Approved',
            'review_notes' => 'Cleared for team training.',
            'athlete_id' => $athlete->id,
        ]);

        $this->actingAs($athlete);

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertSee('Approved')
            ->assertDontSee('Cancelled Training');

        $this->get(route('student.sports'))
            ->assertOk()
            ->assertSee('Basketball')
            ->assertSee('Court sport');

        $this->get(route('student.application'))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee('Cleared for team training.');

        $this->get(route('student.profile'))
            ->assertOk()
            ->assertSee('Grade 10')
            ->assertSee('Female');
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'Student']);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_live_student_activity_and_updates_from_admin_changes(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $otherSport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);
        $admin = User::factory()->create(['role' => 'Administrator']);
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => 'S-2048', 'name' => 'Mika Santos']);
        $application = Application::create([
            'name' => $student->name,
            'student_id' => $student->student_id,
            'grade' => 'Grade 10',
            'gender' => 'Female',
            'email' => $student->email,
            'sport' => $sport->name,
            'sport_id' => $sport->id,
            'status' => 'Documents Required',
            'athlete_id' => $student->id,
        ]);
        $event = Event::create([
            'title' => 'Basketball Training',
            'sport_id' => $sport->id,
            'venue' => 'SNNHS Covered Court',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => 'Scheduled',
        ]);
        $session = AttendanceSession::create([
            'sport_id' => $sport->id,
            'title' => 'Open Basketball Check-in',
            'session_date' => today(),
            'start_time' => '16:00',
            'status' => 'Open',
            'created_by' => $admin->id,
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'Pending',
            'attended_on' => today(),
        ]);
        $announcement = Announcement::create([
            'title' => 'Basketball Team Update',
            'body' => 'Training begins this week.',
            'sport_id' => $sport->id,
            'published_at' => today(),
            'status' => 'Published',
        ]);
        Announcement::create([
            'title' => 'Volleyball Only Update',
            'body' => 'This message is for another sport.',
            'sport_id' => $otherSport->id,
            'published_at' => today(),
            'status' => 'Published',
        ]);
        $student->notify(new AttendanceNotification('Your SportsHub account has an unread update.', route('student.attendance')));

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Mika Santos!')
            ->assertSee('S-2048')
            ->assertSee('Grade 10')
            ->assertSee('Open Basketball Check-in')
            ->assertSee('ATTENDANCE IS OPEN')
            ->assertSee('Open Basketball Check-in')
            ->assertSee('Check In')
            ->assertSee('Basketball Team Update')
            ->assertDontSee('Volleyball Only Update')
            ->assertSee('Incomplete')
            ->assertSee('1 unread');

        $event->update(['status' => 'Cancelled']);
        $session->update(['status' => 'Closed']);
        $announcement->update(['status' => 'Draft']);
        $application->update(['status' => 'Approved']);

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Basketball Training')
            ->assertDontSee('ATTENDANCE IS OPEN')
            ->assertDontSee('Basketball Team Update')
            ->assertSee('Approved');
    }
}
