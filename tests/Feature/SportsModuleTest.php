<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_search_and_view_sport_details(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-1001']);
        Coach::create(['name' => 'Coach Santos', 'email' => 'coach@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id]);
        Event::create(['title' => 'Basketball Practice', 'sport_id' => $sport->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'venue' => 'Main Court', 'status' => 'Scheduled']);
        Application::create(['name' => 'Basketball Applicant', 'student_id' => '2026-1002', 'grade' => 'Grade 10', 'gender' => 'Female', 'email' => 'applicant@example.com', 'sport' => 'Basketball', 'sport_id' => $sport->id, 'status' => 'Pending']);

        $this->actingAs($admin)->get(route('sports.index', ['search' => 'Basketball', 'status' => 'Active']))
            ->assertOk()
            ->assertSee('Basketball');
        $this->actingAs($admin)->get(route('sports.show', $sport))
            ->assertOk()
            ->assertSee('Basketball Practice')
            ->assertSee('Coach Santos')
            ->assertSee('Basketball Applicant');
    }

    public function test_referenced_sport_is_deactivated_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Chess', 'classification' => 'Mind Sport', 'description' => 'Strategy sport', 'status' => 'Active']);
        User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);

        $this->actingAs($admin)->delete(route('sports.destroy', $sport));

        $this->assertDatabaseHas('sports', ['id' => $sport->id, 'status' => 'Inactive']);
    }
}
