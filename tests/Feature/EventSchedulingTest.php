<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class EventSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_create_and_view_an_event(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'School basketball program']);
        $coach = Coach::create(['name' => 'Coach Santos', 'email' => 'coach@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id]);

        $response = $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Basketball Practice',
            'sport_id' => $sport->id,
            'event_type' => 'Practice',
            'starts_at' => '2026-10-02T15:00',
            'ends_at' => '2026-10-02T17:00',
            'venue' => 'Main Court',
            'coach_id' => $coach->id,
            'team_name' => 'Boys Basketball',
            'max_participants' => 20,
            'status' => 'Scheduled',
        ]);

        $event = Event::firstOrFail();
        $response->assertRedirect(route('events.index'));
        $this->assertDatabaseHas('events', ['id' => $event->id, 'event_type' => 'Practice', 'coach_id' => $coach->id]);
        $this->actingAs($admin)->get(route('events.show', $event))->assertOk()->assertSee('Basketball Practice');
    }

    public function test_overlapping_events_in_the_same_venue_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'School volleyball program']);
        Event::create([
            'title' => 'Existing Practice',
            'sport_id' => $sport->id,
            'event_type' => 'Practice',
            'starts_at' => '2026-10-02 15:00:00',
            'ends_at' => '2026-10-02 17:00:00',
            'venue' => 'Main Court',
            'status' => 'Scheduled',
        ]);

        $response = $this->from(route('events.create'))->actingAs($admin)->post(route('events.store'), [
            'title' => 'Overlapping Training',
            'sport_id' => $sport->id,
            'event_type' => 'Training',
            'starts_at' => '2026-10-02T16:00',
            'ends_at' => '2026-10-02T18:00',
            'venue' => 'Main Court',
            'status' => 'Scheduled',
        ]);

        $response->assertRedirect(route('events.create'));
        $response->assertSessionHasErrors('venue');
        $this->assertDatabaseCount('events', 1);
    }

    public function test_event_search_and_status_filter_use_database_queries(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Chess', 'classification' => 'Individual', 'description' => 'School chess program']);
        Event::create(['title' => 'Chess Tournament', 'sport_id' => $sport->id, 'event_type' => 'Tournament', 'starts_at' => '2026-10-03 09:00:00', 'ends_at' => '2026-10-03 12:00:00', 'venue' => 'Library', 'status' => 'Completed']);
        Event::create(['title' => 'Chess Training', 'sport_id' => $sport->id, 'event_type' => 'Training', 'starts_at' => '2026-10-04 09:00:00', 'ends_at' => '2026-10-04 12:00:00', 'venue' => 'Library', 'status' => 'Scheduled']);

        $response = $this->actingAs($admin)->get(route('events.index', ['search' => 'Tournament', 'status' => 'Completed']));

        $response->assertOk()->assertSee('Chess Tournament')->assertDontSee('Chess Training');
    }
}