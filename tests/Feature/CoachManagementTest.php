<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_create_search_and_view_a_coach_profile(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $athlete = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);
        $response = $this->actingAs($admin)->post(route('coaches.store'), ['name' => 'Coach Santos', 'email' => 'coach@example.com', 'phone' => '09171234567', 'specialty' => 'Basketball', 'sport_id' => $sport->id, 'coach_type' => 'Head Coach', 'status' => 'Active']);
        $response->assertRedirect(route('coaches.index'));
        $coach = Coach::firstOrFail();
        Event::create(['title' => 'Basketball Practice', 'sport_id' => $sport->id, 'coach_id' => $coach->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'venue' => 'Main Court', 'status' => 'Scheduled']);

        $this->actingAs($admin)->get(route('coaches.index', ['search' => 'Santos', 'status' => 'Active']))->assertOk()->assertSee('Coach Santos');
        $this->actingAs($admin)->get(route('coaches.show', $coach))->assertOk()->assertSee('Basketball Practice')->assertSee($athlete->name);
    }

    public function test_related_coach_is_deactivated_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport']);
        User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);
        $coach = Coach::create(['name' => 'Coach Reyes', 'email' => 'reyes@example.com', 'specialty' => 'Volleyball', 'sport_id' => $sport->id, 'coach_type' => 'Assistant Coach', 'status' => 'Active']);

        $this->actingAs($admin)->delete(route('coaches.destroy', $coach));

        $this->assertDatabaseHas('coaches', ['id' => $coach->id, 'status' => 'Inactive']);
    }
}
