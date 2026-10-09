<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the announcement -> notification flow described in the feature spec:
 * an admin posts an announcement, it becomes visible to athletes, and a
 * database notification is created for everyone in the audience.
 */
class AnnouncementNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function sport(string $name): Sport
    {
        return Sport::create([
            'name' => $name,
            'classification' => 'Team Sport',
            'description' => $name.' program',
            'status' => 'Active',
        ]);
    }

    private function athlete(Sport $sport, string $name, string $studentId): User
    {
        return User::factory()->create([
            'role' => 'Student',
            'sport_id' => $sport->id,
            'name' => $name,
            'student_id' => $studentId,
            'status' => 'Active',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Administrator']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postAnnouncement(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.announcements.store'), array_merge([
            'title' => 'Basketball Training',
            'body' => 'Training tomorrow at 4 PM.',
            'sport_id' => null,
            'published_at' => today()->toDateString(),
            'status' => 'Published',
        ], $overrides));
    }

    /**
     * Test 1 - a new announcement reaches athletes and produces one notification
     * each, carrying the announcement title and body.
     */
    public function test_published_announcement_reaches_athletes_and_creates_notifications(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');

        $this->postAnnouncement()
            ->assertRedirect(route('admin.announcements'));

        $this->assertDatabaseHas('announcements', ['title' => 'Basketball Training']);

        $this->actingAs($athlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertSee('Training tomorrow at 4 PM.');

        $notification = $athlete->notifications()->firstOrFail();
        $this->assertSame('New announcement', $notification->data['title']);
        $this->assertStringContainsString('Basketball Training', $notification->data['message']);
        $this->assertSame(
            Announcement::firstOrFail()->id,
            $notification->data['announcement_id'],
        );
        $this->assertNull($notification->read_at);

        // The bell surfaces the unread count from the database.
        $this->actingAs($athlete)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('1 unread')
            ->assertSee('Basketball Training');
    }

    /**
     * Test 2 - opening a notification marks it read, drops the unread count and
     * lands on the announcement the notification points at.
     */
    public function test_opening_a_notification_marks_it_read_and_opens_the_announcement(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $this->postAnnouncement();
        $announcement = Announcement::firstOrFail();
        $notification = $athlete->notifications()->firstOrFail();

        $this->assertSame(1, $athlete->unreadNotifications()->count());

        $this->actingAs($athlete)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('student.announcements', ['announcement' => $announcement->id]));

        $this->assertNotNull($athlete->notifications()->whereKey($notification->id)->firstOrFail()->read_at);
        $this->assertSame(0, $athlete->unreadNotifications()->count());

        // The announcement itself is still readable, and the bell badge is gone.
        $this->actingAs($athlete)->get(route('student.announcements', ['announcement' => $announcement->id]))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertSee('0 unread');

        // Read notifications stay in the history.
        $this->actingAs($athlete)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Basketball Training');
    }

    /**
     * Test 3 - every athlete receives their own notification record.
     */
    public function test_each_athlete_receives_their_own_notification_record(): void
    {
        $sport = $this->sport('Basketball');
        $athletes = collect([
            $this->athlete($sport, 'Mika Santos', 'S-2048'),
            $this->athlete($sport, 'Rina Dela Cruz', 'S-2049'),
            $this->athlete($sport, 'Paolo Reyes', 'S-2050'),
        ]);

        $this->postAnnouncement();

        foreach ($athletes as $athlete) {
            $this->assertSame(1, $athlete->notifications()->count());
            $this->assertSame('New announcement', $athlete->notifications()->firstOrFail()->data['title']);
        }

        $this->assertSame(3, User::where('role', 'Student')->get()->sum(fn (User $athlete) => $athlete->notifications()->count()));
    }

    /**
     * Test 4 - a sport targeted announcement only reaches that sport.
     */
    public function test_sport_targeted_announcement_only_reaches_that_sport(): void
    {
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');
        $athletics = $this->sport('Athletics');

        $basketballAthlete = $this->athlete($basketball, 'Mika Santos', 'S-2048');
        $volleyballAthlete = $this->athlete($volleyball, 'Rina Dela Cruz', 'S-2049');
        $athleticsAthlete = $this->athlete($athletics, 'Paolo Reyes', 'S-2050');

        $this->postAnnouncement([
            'title' => 'Basketball Training Tomorrow',
            'body' => 'Game at 4 PM.',
            'sport_id' => $basketball->id,
        ]);

        $this->assertSame(1, $basketballAthlete->notifications()->count());
        $this->assertSame(0, $volleyballAthlete->notifications()->count());
        $this->assertSame(0, $athleticsAthlete->notifications()->count());

        $this->actingAs($basketballAthlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('Basketball Training Tomorrow');

        $this->actingAs($volleyballAthlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertDontSee('Basketball Training Tomorrow');
    }

    /**
     * Test 4b - an athlete who belongs to the sport through an approved
     * application (rather than their account sport) is included too.
     */
    public function test_announcement_reaches_athletes_linked_through_an_approved_application(): void
    {
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');
        $athlete = $this->athlete($volleyball, 'Mika Santos', 'S-2048');

        Application::create([
            'name' => $athlete->name,
            'student_id' => $athlete->student_id,
            'grade' => 'Grade 10',
            'gender' => 'Prefer not to say',
            'email' => $athlete->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'status' => 'Approved',
            'athlete_id' => $athlete->id,
        ]);

        $this->postAnnouncement([
            'title' => 'Basketball Training Tomorrow',
            'sport_id' => $basketball->id,
        ]);

        $this->assertSame(1, $athlete->notifications()->count());
        $this->actingAs($athlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('Basketball Training Tomorrow');
    }

    /**
     * Test 5 - editing an already published announcement never stacks up a
     * second notification, however many times it is saved.
     */
    public function test_editing_a_published_announcement_does_not_duplicate_notifications(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $this->postAnnouncement();
        $announcement = Announcement::firstOrFail();

        $this->assertSame(1, $athlete->notifications()->count());

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), [
                'title' => 'Basketball Training',
                'body' => 'Training tomorrow at 4 PM. Edit '.$attempt,
                'sport_id' => null,
                'published_at' => today()->toDateString(),
                'status' => 'Published',
            ])->assertRedirect();
        }

        $this->assertSame(1, $athlete->notifications()->count());

        // Re-saving an unrelated field is equally harmless.
        $this->actingAs($this->admin())->post(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.announcements.publish', $announcement))->assertRedirect();

        $this->assertSame(1, $athlete->notifications()->count());
    }

    /**
     * Test 5b - an announcement saved as a draft stays silent, and publishing it
     * later notifies exactly once.
     */
    public function test_drafts_do_not_notify_and_publishing_notifies_once(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');

        $this->postAnnouncement(['status' => 'Draft']);

        $this->assertSame(0, $athlete->notifications()->count());
        $this->actingAs($athlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('No announcements yet.');

        $announcement = Announcement::firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.announcements.publish', $announcement))->assertRedirect();

        $this->assertSame(1, $athlete->notifications()->count());

        // Repeating the publish action must not add another notification.
        $this->actingAs($this->admin())->post(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->assertSame(1, $athlete->notifications()->count());

        $this->actingAs($athlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('Basketball Training');
    }

    /**
     * Test 5c - unpublishing tells the audience once, then stays quiet.
     */
    public function test_unpublishing_an_announcement_notifies_the_audience_once(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $this->postAnnouncement();
        $announcement = Announcement::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.announcements.archive', $announcement))->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.announcements.archive', $announcement))->assertRedirect();

        $this->assertSame(2, $athlete->notifications()->count());

        // Both notifications share a created_at second, so assert on the set
        // rather than on ordering.
        $titles = $athlete->notifications()->get()->pluck('data')->pluck('title')->sort()->values()->all();
        $this->assertSame(['Announcement update', 'New announcement'], $titles);
    }

    /**
     * Tests 6 and 7 - state is persisted: reloading the dashboard and logging
     * back in both keep the announcement visible, the notification stored and
     * the unread count intact.
     */
    public function test_announcement_and_notifications_survive_reload_and_relogin(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $this->postAnnouncement();
        $notificationId = $athlete->notifications()->firstOrFail()->id;

        $this->actingAs($athlete);

        foreach (range(1, 3) as $ignored) {
            $this->get(route('student.dashboard'))
                ->assertOk()
                ->assertSee('Basketball Training')
                ->assertSee('1 unread');
        }

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.submit'), [
            'username' => $athlete->email,
            'password' => 'password',
            'role' => 'Student',
        ])->assertRedirect(route('student.dashboard'));

        $this->actingAs($athlete)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Basketball Training')
            ->assertSee('1 unread');

        $this->assertNull($athlete->notifications()->whereKey($notificationId)->firstOrFail()->read_at);

        // Still exactly one announcement notification after all of that.
        $this->assertSame(1, $athlete->unreadNotifications()->count());
    }

    /**
     * The bell lives in the shared layout, so athletes see their own unread
     * count on every portal page - and never a badge when nothing is unread.
     */
    public function test_unread_badge_is_rendered_from_the_database_on_every_portal_page(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');

        foreach ([route('student.dashboard'), route('student.announcements'), route('student.schedule')] as $url) {
            $this->actingAs($athlete)->get($url)
                ->assertOk()
                ->assertSee('0 unread')
                ->assertDontSee('View all notifications</a>'.PHP_EOL.'                        </div>', false);
        }

        $this->postAnnouncement();

        foreach ([route('student.dashboard'), route('student.announcements'), route('student.schedule')] as $url) {
            $this->actingAs($athlete)->get($url)
                ->assertOk()
                ->assertSee('1 unread')
                ->assertSee('Basketball Training');
        }

        $this->actingAs($athlete)->post(route('notifications.read-all'))->assertRedirect();

        foreach ([route('student.dashboard'), route('student.announcements')] as $url) {
            $this->actingAs($athlete)->get($url)
                ->assertOk()
                ->assertSee('0 unread');
        }
    }

    /**
     * The admin portal gets the same bell, which is how coordinators see the
     * check-in notifications they already receive.
     */
    public function test_admin_portal_also_exposes_the_notification_bell(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('0 unread')
            ->assertSee('Notifications');

        $admin->notify(new \App\Notifications\AttendanceNotification('A student checked in.', route('admin.attendance')));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 unread');
    }

    /**
     * Section 18 - authorisation: only admins manage announcements, athletes
     * may only read them, and nobody can touch another athlete's notification.
     */
    public function test_only_administrators_can_manage_announcements(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $announcement = Announcement::create([
            'title' => 'Basketball Training',
            'body' => 'Training tomorrow at 4 PM.',
            'published_at' => today(),
            'status' => 'Published',
        ]);

        $this->actingAs($athlete)->get(route('admin.announcements'))->assertForbidden();

        $this->actingAs($athlete)->post(route('admin.announcements.store'), [
            'title' => 'Sneaky',
            'body' => 'Not allowed.',
            'status' => 'Published',
        ])->assertForbidden();

        $this->actingAs($athlete)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Hijacked',
            'body' => 'Not allowed.',
            'status' => 'Published',
        ])->assertForbidden();

        $this->actingAs($athlete)->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.announcements.publish', $announcement))->assertForbidden();

        $this->assertDatabaseHas('announcements', ['title' => 'Basketball Training']);
    }

    public function test_athletes_cannot_open_or_clear_another_athletes_notification(): void
    {
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $other = $this->athlete($sport, 'Rina Dela Cruz', 'S-2049');
        $this->postAnnouncement();
        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($athlete)->get(route('notifications.open', $otherNotification->id))->assertNotFound();
        $this->actingAs($athlete)->post(route('notifications.read', $otherNotification->id))->assertNotFound();

        $this->assertNull($other->notifications()->whereKey($otherNotification->id)->firstOrFail()->read_at);
    }

    public function test_notification_routes_require_authentication(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
        $this->get(route('notifications.open', 'some-id'))->assertRedirect(route('login'));
    }

    /**
     * The admin form must actually expose the targeting controls the controller
     * validates, otherwise sport targeting is unusable.
     */
    public function test_admin_form_exposes_target_sport_and_publish_date(): void
    {
        $basketball = $this->sport('Basketball');
        $this->postAnnouncement(['sport_id' => $basketball->id]);

        $this->actingAs($this->admin())->get(route('admin.announcements'))
            ->assertOk()
            ->assertSee('All Athletes')
            ->assertSee($basketball->name)
            ->assertSee('name="sport_id"', false)
            ->assertSee('name="published_at"', false)
            ->assertSee('Post Announcement')
            ->assertSee('Unpublish')
            ->assertSee('Delete');
    }

    /**
     * The admin list can drive the existing publish/archive/delete routes.
     */
    public function test_admin_can_publish_and_delete_through_the_management_routes(): void
    {
        $admin = $this->admin();
        $sport = $this->sport('Basketball');
        $athlete = $this->athlete($sport, 'Mika Santos', 'S-2048');

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Basketball Training',
            'body' => 'Training tomorrow at 4 PM.',
            'sport_id' => $sport->id,
            'published_at' => today()->toDateString(),
            'status' => 'Draft',
        ])->assertRedirect();

        $announcement = Announcement::firstOrFail();
        $this->assertSame(0, $athlete->notifications()->count());

        $this->actingAs($admin)->post(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->assertSame(1, $athlete->notifications()->count());

        $this->actingAs($admin)->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    /**
     * Inactive athletes are not part of the audience for a new announcement.
     */
    public function test_inactive_athletes_are_not_notified(): void
    {
        $sport = $this->sport('Basketball');
        $active = $this->athlete($sport, 'Mika Santos', 'S-2048');
        $inactive = $this->athlete($sport, 'Rina Dela Cruz', 'S-2049');
        $inactive->update(['status' => 'Inactive']);

        $this->postAnnouncement();

        $this->assertSame(1, $active->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
    }
}