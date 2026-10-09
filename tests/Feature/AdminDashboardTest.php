<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Application;
use App\Models\Event;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_database_backed_dashboard_sections(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'School basketball program']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);
        Event::create([
            'title' => 'Basketball Practice',
            'venue' => 'Main Court',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'Scheduled',
            'sport_id' => $sport->id,
        ]);
        Application::create([
            'name' => 'Pending Athlete',
            'email' => 'pending@example.com',
            'student_id' => '2026-9999',
            'grade' => 'Grade 10',
            'gender' => 'Female',
            'sport' => 'Basketball',
            'sport_id' => $sport->id,
            'status' => 'Pending',
        ]);
        MedicalRecord::create([
            'athlete_id' => $athlete->id,
            'user_id' => $athlete->id,
            'examination_date' => today(),
            'medical_status' => 'Cleared',
            'next_checkup_date' => today()->addYear(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Quick Actions');
        $response->assertSee('Upcoming Events');
        $response->assertSee('Basketball Practice');
        $response->assertSee('Pending Sports Applications');
        $response->assertSee('Pending Athlete');
        $response->assertSee('Medical Clearance');
    }

    public function test_admin_dashboard_shows_the_sports_statistics_section(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Sports Statistics & Achievements');
        $response->assertSee('Total Athletes');
        $response->assertSee('Total Sports');
        $response->assertSee('Total Achievements');
        $response->assertSee('Athletes by Sport');
        $response->assertSee('Achievements by Sport');
        $response->assertSee('Achievement Medal Summary');
        $response->assertSee('Top Performing Sports');
        $response->assertSee('Recent Achievements');
        $response->assertSee('Generate Certificate');
    }

    public function test_admin_dashboard_statistics_reflect_the_actual_database_records(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'School basketball program']);
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'School volleyball program', 'status' => 'Active']);
        User::factory()->count(2)->create(['role' => 'Student', 'sport_id' => $basketball->id]);
        User::factory()->create(['role' => 'Student', 'sport_id' => $volleyball->id]);
        Achievement::create([
            'athlete_id' => User::where('role', 'Student')->first()->id,
            'sport_id' => $basketball->id,
            'title' => 'Best Scorer',
            'achievement_type' => 'Gold Medal',
            'place' => '1st',
            'date_achieved' => '2026-08-01',
        ]);
        Achievement::create([
            'athlete_id' => User::where('role', 'Student')->skip(1)->first()->id,
            'sport_id' => $basketball->id,
            'title' => 'Best Defender',
            'achievement_type' => 'Silver Medal',
            'place' => '2nd',
            'date_achieved' => '2026-08-02',
        ]);
        Achievement::create([
            'athlete_id' => User::where('role', 'Student')->skip(2)->first()->id,
            'sport_id' => $volleyball->id,
            'title' => 'MVP',
            'achievement_type' => 'Bronze Medal',
            'place' => '3rd',
            'date_achieved' => '2026-08-03',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Sports Statistics & Achievements');

        $html = $response->getContent();

        $this->assertStringContainsString('Live counts across 2 programs', $html);
        $this->assertStringContainsString('3 athletes assigned to 2 programs', $html);
        $this->assertStringContainsString('3 achievements split across 2 sports', $html);
        $this->assertStringContainsString('3 medals awarded to date', $html);
        $this->assertStringContainsString('Gold Medal', $html);
        $this->assertStringContainsString('Silver Medal', $html);
        $this->assertStringContainsString('Bronze Medal', $html);
        $this->assertStringContainsString('Best Scorer', $html);
        $this->assertStringContainsString('MVP', $html);
    }

    public function test_admin_dashboard_statistics_show_empty_states_when_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No athletes have been registered yet.');
        $response->assertSee('No achievements have been recorded yet.');
        $response->assertSee('Live counts across 0 programs', false);
    }

    public function test_admin_dashboard_recent_achievements_open_the_certificate_routes(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Swimming', 'classification' => 'Individual Sport', 'description' => 'Pool events', 'status' => 'Active']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);
        $achievement = Achievement::create([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'title' => 'Fastest Lap',
            'achievement_type' => 'Gold Medal',
            'place' => '1st',
            'date_achieved' => '2026-07-10',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Fastest Lap');
        $response->assertSee(route('admin.achievements.certificate.print', $achievement));
    }
}
