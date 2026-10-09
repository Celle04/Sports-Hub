<?php

namespace Tests\Feature;

use App\Models\Application;
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

    /**
     * The month grid used to group events by their start date only, so a
     * tournament that ran from the 30th into the 2nd was invisible on the
     * days it was actually being held.
     */
public function test_a_multi_day_event_appears_on_every_day_it_covers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        Event::create([
            'title' => 'District Meet',
            'venue' => 'Main Court',
            'starts_at' => now()->startOfMonth()->addDays(11)->setTime(8, 0),
            'ends_at' => now()->startOfMonth()->addDays(13)->setTime(17, 0),
            'status' => 'Scheduled',
        ]);

        $content = $this->get(route('admin.calendar', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->getContent();

        // One chip per covered day: the 12th, 13th and 14th.
        $this->assertSame(
            3,
            substr_count($content, 'admin-calendar-event-title">District Meet'),
            'a three day event should be rendered on all three days'
        );
    }

    /**
     * An event that runs past the end of the month has to stay visible in both
     * months, without spilling past the days the grid actually shows.
     */
    public function test_a_multi_day_event_is_visible_in_both_months_it_spans(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $monthStart = now()->startOfMonth();

        Event::create([
            'title' => 'Meet Finals',
            'venue' => 'Main Court',
            'starts_at' => $monthStart->copy()->addDays(29)->setTime(8, 0),
            'ends_at' => $monthStart->copy()->addDays(31)->setTime(17, 0),
            'status' => 'Scheduled',
        ]);

        $chips = fn (string $month) => substr_count(
            $this->get(route('admin.calendar', ['month' => $month]))->assertOk()->getContent(),
            'admin-calendar-event-title">Meet Finals'
        );

        // October's grid ends on the 31st, so the event shows on the 30th and 31st.
        $this->assertSame(2, $chips($monthStart->format('Y-m')));

        // November's grid starts on the 1st, where the event is still running.
        $this->assertSame(1, $chips($monthStart->copy()->addMonth()->format('Y-m')));
    }

    /**
     * Filtering must not throw away the month the admin navigated to.
     */
    public function test_filters_keep_the_month_being_viewed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);

        $viewedMonth = now()->subMonths(2);

        $this->get(route('admin.calendar', ['month' => $viewedMonth->format('Y-m'), 'sport_id' => $basketball->id]))
            ->assertOk()
            ->assertSee($viewedMonth->format('F Y'))
            ->assertSee('name="month" value="'.$viewedMonth->format('Y-m').'"', false);
    }

    public function test_calendar_exposes_a_status_filter_and_an_event_type_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        Event::create([
            'title' => 'Cancelled Practice',
            'venue' => 'Main Court',
            'starts_at' => now()->startOfMonth()->addDays(4)->setTime(15, 0),
            'ends_at' => now()->startOfMonth()->addDays(4)->setTime(17, 0),
            'status' => 'Cancelled',
        ]);

        $month = now()->format('Y-m');

        $this->get(route('admin.calendar', ['month' => $month, 'status' => 'Cancelled']))
            ->assertOk()
            ->assertSee('Cancelled Practice');

        $this->get(route('admin.calendar', ['month' => $month, 'status' => 'Scheduled']))
            ->assertOk()
            ->assertDontSee('Cancelled Practice');

        // The event_type filter existed in the query but had no form control.
        $this->get(route('admin.calendar', ['month' => $month, 'event_type' => 'Practice']))
            ->assertOk()
            ->assertDontSee('Cancelled Practice');
    }

    /**
     * Events beyond the visible three must not disappear without explanation.
     */
    public function test_days_with_many_events_report_the_remainder(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $day = now()->startOfMonth()->addDays(9)->setTime(14, 0);

        foreach (range(1, 5) as $index) {
            Event::create([
                'title' => "Session {$index}",
                'venue' => 'Main Court',
                'starts_at' => $day->copy()->addHours($index),
                'ends_at' => $day->copy()->addHours($index)->addHour(),
                'status' => 'Scheduled',
            ]);
        }

        $this->get(route('admin.calendar', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Session 1')
            ->assertSee('+2 more');
    }

    public function test_calendar_labels_the_summary_with_the_month_being_viewed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $this->get(route('admin.calendar'))
            ->assertOk()
            ->assertSee('This Month');

        $this->get(route('admin.calendar', ['month' => now()->subYear()->format('Y-m')]))
            ->assertOk()
            ->assertSee(now()->subYear()->format('M Y'))
            ->assertDontSee('This Month');
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

    /**
     * A student's sport membership can come from an approved application even
     * when it is not their account sport, so events for that sport must show
     * up on their schedule just like events for their account sport.
     */
    public function test_event_for_a_sport_linked_by_approved_application_appears_in_student_schedule(): void
    {
        $eventStart = now()->addDay()->setTime(15, 0);
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball events']);
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Volleyball events']);

        Event::create([
            'title' => 'Basketball Tournament',
            'venue' => 'Main Court',
            'starts_at' => $eventStart,
            'ends_at' => $eventStart->copy()->addHours(2),
            'status' => 'Scheduled',
            'sport_id' => $basketball->id,
        ]);

        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $volleyball->id, 'status' => 'Active']);
        Application::create([
            'name' => $student->name,
            'student_id' => $student->student_id,
            'grade' => 'Grade 10',
            'gender' => 'Male',
            'email' => $student->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'athlete_id' => $student->id,
            'status' => 'Approved',
        ]);

        $this->actingAs($student)->get(route('student.schedule'))
            ->assertOk()
            ->assertSee('Basketball Tournament');
    }

    /**
     * The calendar month view applies the same sport membership rule, so an
     * event for a sport reached through an approved application shows up for
     * the student there too.
     */
    public function test_event_for_a_sport_linked_by_approved_application_appears_in_student_calendar(): void
    {
        $eventStart = now()->startOfMonth()->addDay()->setTime(15, 0);
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Basketball events']);
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Volleyball events']);

        Event::create([
            'title' => 'Basketball Meet',
            'venue' => 'Main Court',
            'starts_at' => $eventStart,
            'ends_at' => $eventStart->copy()->addHours(2),
            'status' => 'Scheduled',
            'sport_id' => $basketball->id,
        ]);

        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $volleyball->id]);
        Application::create([
            'name' => $student->name,
            'student_id' => $student->student_id,
            'grade' => 'Grade 10',
            'gender' => 'Male',
            'email' => $student->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'athlete_id' => $student->id,
            'status' => 'Approved',
        ]);

        $this->actingAs($student)->get(route('student.calendar', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Basketball Meet');
    }
}