<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A scheduled event publishes an announcement on the public landing page.
 */
class LandingAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_creating_a_scheduled_event_publishes_a_landing_page_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball', 'status' => 'Active']);

        $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Basketball Practice',
            'sport_id' => $sport->id,
            'event_type' => 'Practice',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d\TH:i'),
            'venue' => 'Main Court',
            'status' => 'Scheduled',
        ])->assertSessionHas('success');

        $event = Event::firstOrFail();
        $announcement = Announcement::firstOrFail();

        $this->assertSame($event->id, $announcement->event_id);
        $this->assertStringContainsString('Basketball Practice', $announcement->title);
        $this->assertStringContainsString('Main Court', $announcement->body);
        $this->assertSame('Published', $announcement->status);
        $this->assertTrue($announcement->published_at->isToday());

        // The announcement shows on the public landing page.
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('New Event: Basketball Practice')
            ->assertSee('View the event');
    }

    public function test_an_event_that_is_not_scheduled_is_not_announced(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Cancelled Drill',
            'event_type' => 'Training',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addWeek()->addHour()->format('Y-m-d\TH:i'),
            'venue' => 'Gym',
            'status' => 'Cancelled',
        ])->assertSessionHas('success');

        $this->assertSame(0, Announcement::count());
    }

    public function test_the_announcement_of_a_cancelled_event_is_hidden_from_the_landing_page(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Later Cancelled',
            'event_type' => 'Training',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addWeek()->addHour()->format('Y-m-d\TH:i'),
            'venue' => 'Gym',
            'status' => 'Scheduled',
        ])->assertSessionHas('success');

        $this->get(route('landing'))->assertOk()->assertSee('New Event: Later Cancelled');

        // The event is called off, so the announcement steps out of the news.
        Event::firstOrFail()->update(['status' => 'Cancelled']);

        $this->get(route('landing'))->assertOk()->assertDontSee('New Event: Later Cancelled');
    }

    public function test_a_guest_can_see_the_announcements_on_the_landing_page(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Public Meet',
            'event_type' => 'Tournament',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(3)->addHours(3)->format('Y-m-d\TH:i'),
            'venue' => 'Regional Track',
            'status' => 'Scheduled',
        ])->assertSessionHas('success');

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('New Event: Public Meet');
    }
}
