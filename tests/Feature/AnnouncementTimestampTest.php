<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Sport;
use App\Models\User;
use App\Support\RelativeTime;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The "Posted ..." line on an announcement must reflect the moment it was
 * actually created, not a date-only column that resolves to midnight.
 */
class AnnouncementTimestampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function sport(): Sport
    {
        return Sport::create([
            'name' => 'Basketball',
            'classification' => 'Team Sport',
            'description' => 'Court sport',
            'status' => 'Active',
        ]);
    }

    private function athlete(Sport $sport): User
    {
        return User::factory()->create([
            'role' => 'Student',
            'sport_id' => $sport->id,
            'status' => 'Active',
            'student_id' => 'S-2048',
        ]);
    }

    /**
     * Post an announcement through the real admin route, then backdate its
     * creation so relative text can be asserted deterministically.
     */
    private function postAgedAnnouncement(User $admin, Sport $sport, Carbon $createdAt, string $title): Announcement
    {
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => $title,
            'body' => 'Training tomorrow at 4 PM.',
            'sport_id' => $sport->id,
            'published_at' => today()->toDateString(),
            'status' => 'Published',
        ])->assertRedirect();

        $announcement = Announcement::where('title', $title)->firstOrFail();

        $announcement->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $announcement->fresh();
    }

    /**
     * Test 1 - a freshly posted announcement reads as just posted, not as a
     * time-of-day value measured from midnight.
     */
    public function test_new_announcement_reads_as_just_posted(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();
        $athlete = $this->athlete($sport);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Basketball Training',
            'body' => 'Training tomorrow at 4 PM.',
            'sport_id' => $sport->id,
            'published_at' => today()->toDateString(),
            'status' => 'Published',
        ])->assertRedirect();

        $announcement = Announcement::where('title', 'Basketball Training')->firstOrFail();

        $this->assertSame('Just now', $announcement->postedForHumans());
        $this->assertNotSame('1 hour ago', $announcement->postedForHumans());

        $this->actingAs($athlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('Posted Just now');

        $this->actingAs($athlete)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Posted Just now');
    }

    /**
     * The regression that caused the bug: published_at is a DATE column, so it
     * resolves to midnight and must never drive the relative time.
     */
    public function test_published_at_date_column_is_not_used_as_the_posting_time(): void
    {
        $sport = $this->sport();
        $admin = User::factory()->create(['role' => 'Administrator']);
        $announcement = $this->postAgedAnnouncement(
            $admin,
            $sport,
            Carbon::now()->subMinutes(7),
            'Aged announcement'
        );

        $this->assertSame('7 minutes ago', $announcement->postedForHumans());

        // The date column really does resolve to midnight, which is the trap.
        $this->assertTrue($announcement->published_at->isMidnight());
        $this->assertNotNull($announcement->published_at);
        $this->assertSame($announcement->created_at->toDateTimeString(), $announcement->postedAt()->toDateTimeString());
    }

    /**
     * Test 3 - each announcement reports its own age.
     */
    public function test_each_announcement_reports_its_own_age(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();

        $ages = ['A' => 0, 'B' => 180, 'C' => 900, 'D' => 3600, 'E' => 86400];
        $expected = [
            'A' => 'Just now',
            'B' => '3 minutes ago',
            'C' => '15 minutes ago',
            'D' => '1 hour ago',
            'E' => '1 day ago',
        ];

        foreach ($ages as $key => $secondsAgo) {
            $announcement = $this->postAgedAnnouncement(
                $admin,
                $sport,
                Carbon::now()->subSeconds($secondsAgo),
                "Announcement {$key}"
            );

            $this->assertSame($expected[$key], $announcement->postedForHumans(), "Announcement {$key}");
        }
    }

    /**
     * Test 4 - editing must not restart the clock.
     */
    public function test_editing_an_announcement_keeps_the_original_posting_time(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();
        $announcement = $this->postAgedAnnouncement(
            $admin,
            $sport,
            Carbon::now()->subMinutes(20),
            'Basketball Training'
        );

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Basketball Training (edited)',
            'body' => 'Training tomorrow at 5 PM instead.',
            'sport_id' => $sport->id,
            'published_at' => today()->toDateString(),
            'status' => 'Published',
        ])->assertRedirect();

        $edited = $announcement->fresh();

        $this->assertTrue($edited->updated_at->gt($edited->created_at), 'the edit should have moved updated_at');
        $this->assertSame('20 minutes ago', $edited->postedForHumans());
        $this->assertSame($announcement->created_at->toDateTimeString(), $edited->created_at->toDateTimeString());
    }

    /**
     * Test 5 - the value survives a reload and is stable, never random.
     */
    public function test_posting_time_is_stable_across_reloads(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();
        $athlete = $this->athlete($sport);
        $this->postAgedAnnouncement($admin, $sport, Carbon::now()->subMinutes(12), 'Stable announcement');

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($athlete)->get(route('student.announcements'))
                ->assertOk()
                ->assertSee('Posted 12 minutes ago')
                ->assertDontSee('Posted Just now');
        }
    }

    /**
     * Test 6 - the notification carries its own accurate relative time.
     */
    public function test_notification_relative_time_is_accurate(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();
        $athlete = $this->athlete($sport);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Basketball Training',
            'body' => 'Training tomorrow at 4 PM.',
            'sport_id' => $sport->id,
            'published_at' => today()->toDateString(),
            'status' => 'Published',
        ])->assertRedirect();

        $this->actingAs($athlete)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Just now');

        // Age the notification itself and confirm the bell follows it.
        $notification = $athlete->notifications()->firstOrFail();
        DB::table('notifications')->where('id', $notification->id)->update([
            'created_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHours(3),
        ]);

        $this->actingAs($athlete)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('3 hours ago');
    }

    /**
     * Test 7 - the database value, the model value and the rendered text agree,
     * with the school operating in Philippine Standard Time.
     */
    public function test_timestamps_are_consistent_in_philippine_standard_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));

        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();
        $announcement = $this->postAgedAnnouncement(
            $admin,
            $sport,
            Carbon::now()->subMinutes(4),
            'Timezone probe'
        );

        $raw = DB::table('announcements')->where('id', $announcement->id)->value('created_at');

        // No offset is applied when reading back: raw value equals model value.
        $this->assertSame($raw, $announcement->created_at->toDateTimeString());
        $this->assertEqualsWithDelta(4, Carbon::parse($raw)->diffInMinutes(), 1);
        $this->assertSame('4 minutes ago', $announcement->postedForHumans());

        // The stored instant is still the correct absolute moment in time.
        $this->assertTrue(
            Carbon::now()->subMinutes(4)->between(
                Carbon::parse($raw)->copy()->subMinute(),
                Carbon::parse($raw)->copy()->addMinute(),
            ),
            'the stored timestamp should represent the real posting instant'
        );
    }

    /**
     * Test 12 - a missing timestamp degrades gracefully.
     */
    public function test_missing_timestamp_falls_back_without_throwing(): void
    {
        $announcement = new Announcement(['title' => 'No timestamps yet']);

        $this->assertNull($announcement->postedAt());
        $this->assertSame('Date unavailable', $announcement->postedForHumans());
        $this->assertNull(RelativeTime::machine(null));
        $this->assertSame('Date unavailable', RelativeTime::of(null));
    }

    /**
     * The relative time ladder required by the feature.
     */
    public function test_relative_time_ladder(): void
    {
        $ladder = [
            0 => 'Just now',
            5 => 'Just now',
            45 => 'Just now',
            59 => 'Just now',
            60 => '1 minute ago',
            120 => '2 minutes ago',
            300 => '5 minutes ago',
            600 => '10 minutes ago',
            3600 => '1 hour ago',
            7200 => '2 hours ago',
            86400 => '1 day ago',
            172800 => '2 days ago',
        ];

        foreach ($ladder as $secondsAgo => $expected) {
            $this->assertSame(
                $expected,
                RelativeTime::of(Carbon::now()->subSeconds($secondsAgo)),
                "{$secondsAgo} seconds ago"
            );
        }
    }

    /**
     * No template may invent a relative time; they all come from the formatter.
     */
    public function test_no_hardcoded_relative_time_strings_in_views(): void
    {
        $forbidden = ['hour ago', 'minutes ago', 'days ago', 'time ago'];

        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($views as $view) {
            if (! $view->isFile() || $view->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($view->getPathname());

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    $view->getFilename().' contains a hardcoded relative time'
                );
            }
        }
    }

    /**
     * Live refresh support: each relative timestamp carries a machine readable
     * value so the browser can recompute it without a reload.
     */
    public function test_relative_timestamps_expose_a_machine_readable_value(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = $this->sport();
        $athlete = $this->athlete($sport);
        $announcement = $this->postAgedAnnouncement(
            $admin,
            $sport,
            Carbon::now()->subMinutes(2),
            'Live timestamp'
        );

        $this->actingAs($athlete)->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('data-posted-at="'.$announcement->created_at->toIso8601String().'"', false)
            ->assertSee('data-posted-prefix="Posted "', false)
            ->assertSee('Posted 2 minutes ago');

        $this->actingAs($athlete)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('relative-time.js', false);
    }
}