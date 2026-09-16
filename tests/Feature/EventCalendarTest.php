<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_event_and_invalid_time_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));
        $this->post(route('events.store'), ['title' => 'Practice', 'venue' => 'Main Court', 'starts_at' => '2026-08-30T15:00', 'ends_at' => '2026-08-30T17:00', 'status' => 'Scheduled'])->assertRedirect(route('events.index'));
        $event = Event::firstOrFail();
        $this->assertDatabaseHas('events', ['title' => 'Practice']);
        $this->get(route('admin.calendar', ['month' => '2026-08']))->assertOk()->assertSee('Practice');
        $this->post(route('events.store'), ['title' => 'Invalid', 'venue' => 'Main Court', 'starts_at' => '2026-08-30T17:00', 'ends_at' => '2026-08-30T15:00', 'status' => 'Scheduled'])->assertSessionHasErrors('ends_at');
        $this->delete(route('events.destroy', $event))->assertRedirect(route('events.index'));
    }

    public function test_student_can_view_calendar_but_cannot_manage_events(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Student']));

        $this->get(route('student.calendar'))->assertOk();
        $this->get(route('events.index'))->assertForbidden();
        $this->post(route('events.store'))->assertForbidden();
    }

    public function test_scheduled_admin_event_appears_in_student_schedule(): void
    {
        $eventStart = now()->addDay()->setTime(15, 0);
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball events']);
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Volleyball events']);

        $this->actingAs(User::factory()->create(['role' => 'Administrator']));
        $this->post(route('events.store'), [
            'title' => 'Admin Created Practice',
            'sport_id' => $basketball->id,
            'venue' => 'Main Court',
            'starts_at' => $eventStart->format('Y-m-d\\TH:i'),
            'ends_at' => $eventStart->copy()->addHours(2)->format('Y-m-d\\TH:i'),
            'status' => 'Scheduled',
        ])->assertRedirect(route('events.index'));

        Event::create([
            'title' => 'Cancelled Practice',
            'venue' => 'Main Court',
            'starts_at' => $eventStart,
            'ends_at' => $eventStart->copy()->addHours(2),
            'status' => 'Cancelled',
        ]);

        Event::create([
            'title' => 'Volleyball Practice',
            'venue' => 'Gymnasium',
            'starts_at' => $eventStart,
            'ends_at' => $eventStart->copy()->addHours(2),
            'status' => 'Scheduled',
            'sport_id' => $volleyball->id,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id]));
        $this->get(route('student.schedule'))
            ->assertOk()
            ->assertSee('Admin Created Practice')
            ->assertDontSee('Cancelled Practice')
            ->assertDontSee('Volleyball Practice');
    }
}