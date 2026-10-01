<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
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
}
