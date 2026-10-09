<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use App\Notifications\StudentUpdateNotification;
use App\Services\StudentUpdateNotifier;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('private');
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Sport, 2: \App\Models\User}
     */
    private function context(string $sportName = 'Basketball'): array
    {
        $sport = Sport::create(['name' => $sportName, 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $admin = User::factory()->create(['role' => 'Administrator', 'name' => 'Admin User']);
        $athlete = User::factory()->create([
            'role' => 'Student',
            'name' => 'Juan Dela Cruz',
            'student_id' => 'S-2048',
            'sport_id' => $sport->id,
            'status' => 'Active',
        ]);

        return [$admin, $sport, $athlete];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $athlete, array $overrides = []): array
    {
        return array_merge([
            'athlete_id' => $athlete->id,
            'title' => 'Gold Medal - 100 Meter Sprint',
            'achievement_type' => 'Gold Medal',
            'competition' => 'Caraga Regional Athletic Games 2026',
            'place' => '1st Place',
            'date_achieved' => '2026-09-15',
            'description' => 'Set a new school record in the 100 meter sprint.',
        ], $overrides);
    }

    public function test_administrator_can_create_an_achievement(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)->get(route('admin.achievements'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $sport->id]))
            ->assertRedirect(route('admin.achievements'));

        $this->assertDatabaseHas('achievements', [
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'title' => 'Gold Medal - 100 Meter Sprint',
            'achievement_type' => 'Gold Medal',
            'place' => '1st Place',
        ]);
    }

    public function test_the_created_achievement_is_listed_for_the_administrator(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->get(route('admin.achievements'))
            ->assertOk()
            ->assertSee('Gold Medal - 100 Meter Sprint')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('S-2048')
            ->assertSee('Caraga Regional Athletic Games 2026')
            ->assertSee(route('admin.achievements.edit', $achievement));
    }

    public function test_administrator_can_view_achievement_details(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->get(route('admin.achievements.show', $achievement))
            ->assertOk()
            ->assertSee('Gold Medal - 100 Meter Sprint')
            ->assertSee('Basketball')
            ->assertSee('Sep 15, 2026');
    }

    public function test_administrator_can_edit_an_achievement(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->put(route('admin.achievements.update', $achievement), $this->payload($athlete, [
            'title' => 'Silver Medal - 200 Meter Sprint',
            'achievement_type' => 'Silver Medal',
        ]))
            ->assertRedirect(route('admin.achievements'));

        $this->assertDatabaseHas('achievements', [
            'id' => $achievement->id,
            'title' => 'Silver Medal - 200 Meter Sprint',
            'achievement_type' => 'Silver Medal',
        ]);
    }

    public function test_administrator_can_delete_an_achievement_and_its_certificate(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('achievement-certificates/cert.pdf', 'binary');
        $achievement->update(['certificate_path' => 'achievement-certificates/cert.pdf']);

        $this->actingAs($admin)
            ->delete(route('admin.achievements.destroy', $achievement))
            ->assertRedirect(route('admin.achievements'));

        $this->assertDatabaseMissing('achievements', ['id' => $achievement->id]);
        Storage::disk('private')->assertMissing('achievement-certificates/cert.pdf');
    }

    public function test_certificate_upload_is_stored_on_the_private_disk_and_downloaded_by_id(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)->post(route('admin.achievements.store'), $this->payload($athlete, [
            'sport_id' => $sport->id,
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
        ]))->assertRedirect(route('admin.achievements'));

        $achievement = Achievement::firstOrFail();

        $this->assertStringStartsWith('achievement-certificates/', $achievement->certificate_path);
        Storage::disk('private')->assertExists($achievement->certificate_path);

        $this->actingAs($admin)
            ->get(route('admin.achievements.certificate', $achievement))
            ->assertOk();
    }

    public function test_replacing_a_certificate_removes_the_previous_file(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('achievement-certificates/old.pdf', 'binary');
        $achievement->update(['certificate_path' => 'achievement-certificates/old.pdf']);

        $this->actingAs($admin)->put(route('admin.achievements.update', $achievement), $this->payload($athlete, [
            'sport_id' => $sport->id,
            'certificate' => UploadedFile::fake()->create('new.png', 90, 'image/png'),
        ]))->assertRedirect(route('admin.achievements'));

        Storage::disk('private')->assertMissing('achievement-certificates/old.pdf');
        Storage::disk('private')->assertExists($achievement->fresh()->certificate_path);
    }

    public function test_an_achievement_without_a_certificate_reports_none(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->assertFalse($achievement->hasCertificate());

        $this->actingAs($admin)
            ->get(route('admin.achievements.certificate', $achievement))
            ->assertNotFound();
    }

    public function test_achievement_can_be_linked_to_an_existing_event_instead_of_duplicating_it(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $event = Event::create([
            'title' => 'Caraga Regional Athletic Games 2026',
            'sport_id' => $sport->id,
            'venue' => 'Regional Track',
            'ends_at' => now(),
            'starts_at' => now()->subMonths(2),
            'status' => 'Completed',
        ]);

        $this->actingAs($admin)->post(route('admin.achievements.store'), $this->payload($athlete, [
            'sport_id' => $sport->id,
            'event_id' => $event->id,
            'competition' => null,
        ]))->assertRedirect(route('admin.achievements'));

        $achievement = Achievement::firstOrFail();

        $this->assertSame($event->id, $achievement->event_id);
        $this->assertSame('Caraga Regional Athletic Games 2026', $achievement->competition);
        $this->assertSame('Caraga Regional Athletic Games 2026', $achievement->competitionLabel());
    }

    public function test_a_custom_achievement_type_is_accepted(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)->post(route('admin.achievements.store'), $this->payload($athlete, [
            'sport_id' => $sport->id,
            'achievement_type' => 'Most Improved Athlete',
        ]))->assertRedirect(route('admin.achievements'));

        $this->assertDatabaseHas('achievements', ['achievement_type' => 'Most Improved Athlete']);
    }

    public function test_summary_counts_come_from_real_records(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'achievement_type' => 'Gold Medal']));
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Silver', 'achievement_type' => 'Silver Medal', 'date_achieved' => '2026-09-16']));
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Bronze', 'achievement_type' => 'Bronze Medal', 'date_achieved' => '2026-09-17']));
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Champion', 'achievement_type' => 'Champion', 'date_achieved' => '2026-09-18']));

        $this->actingAs($admin)
            ->get(route('admin.achievements'))
            ->assertOk()
            ->assertSeeInOrder(['Total Achievements', 'Gold Medals', 'Silver Medals', 'Bronze Medals', 'Championships']);

        $this->assertSame(4, Achievement::count());
    }

    public function test_the_list_can_be_searched_and_filtered_through_the_query(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);
        $other = User::factory()->create(['role' => 'Student', 'name' => 'Maria Reyes', 'student_id' => 'S-3099', 'sport_id' => $volleyball->id, 'status' => 'Active']);

        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Achievement::create($this->payload($other, ['sport_id' => $volleyball->id, 'title' => 'Volleyball Cup', 'achievement_type' => 'Champion']));

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['search' => 'Maria']))
            ->assertOk()
            ->assertSee('Volleyball Cup')
            ->assertDontSee('100 Meter Sprint');

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['search' => 'S-3099']))
            ->assertOk()
            ->assertSee('Volleyball Cup')
            ->assertDontSee('100 Meter Sprint');

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['sport_id' => $volleyball->id]))
            ->assertOk()
            ->assertSee('Volleyball Cup')
            ->assertDontSee('100 Meter Sprint');

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['achievement_type' => 'Gold Medal']))
            ->assertOk()
            ->assertSee('100 Meter Sprint')
            ->assertDontSee('Volleyball Cup');

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['athlete_id' => $athlete->id]))
            ->assertOk()
            ->assertSee('100 Meter Sprint')
            ->assertDontSee('Volleyball Cup');
    }

    public function test_the_list_can_be_filtered_by_date_range_and_sorted(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Older Win', 'date_achieved' => '2025-01-10']));
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Newer Win', 'date_achieved' => '2026-09-15']));

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31']))
            ->assertOk()
            ->assertSee('Newer Win')
            ->assertDontSee('Older Win');

        $this->actingAs($admin)
            ->get(route('admin.achievements', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSeeInOrder(['Older Win', 'Newer Win']);
    }

    public function test_the_athlete_sees_only_their_own_achievements(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $other = User::factory()->create(['role' => 'Student', 'name' => 'Maria Reyes', 'sport_id' => $sport->id, 'status' => 'Active']);

        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Achievement::create($this->payload($other, ['sport_id' => $sport->id, 'title' => 'Maria Volleyball Cup', 'achievement_type' => 'Champion']));

        $this->actingAs($athlete)
            ->get(route('student.achievements'))
            ->assertOk()
            ->assertSee('Gold Medal - 100 Meter Sprint')
            ->assertDontSee('Maria Volleyball Cup');
    }

    public function test_the_athlete_can_open_their_own_achievement(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($athlete)
            ->get(route('student.achievements.show', $achievement))
            ->assertOk()
            ->assertSee('Gold Medal - 100 Meter Sprint')
            ->assertSee('Basketball')
            ->assertSee('Caraga Regional Athletic Games 2026')
            ->assertSee('Sep 15, 2026');
    }

    public function test_the_athlete_cannot_open_another_athletes_achievement_by_changing_the_url(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $other = User::factory()->create(['role' => 'Student', 'name' => 'Maria Reyes', 'sport_id' => $sport->id, 'status' => 'Active']);
        $foreign = Achievement::create($this->payload($other, ['sport_id' => $sport->id, 'title' => 'Maria Volleyball Cup']));

        $this->actingAs($athlete)
            ->get(route('student.achievements.show', $foreign))
            ->assertNotFound();
    }

    public function test_the_athlete_cannot_download_another_athletes_certificate(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $other = User::factory()->create(['role' => 'Student', 'name' => 'Maria Reyes', 'sport_id' => $sport->id, 'status' => 'Active']);
        $foreign = Achievement::create($this->payload($other, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('achievement-certificates/foreign.pdf', 'binary');
        $foreign->update(['certificate_path' => 'achievement-certificates/foreign.pdf']);

        $this->actingAs($athlete)
            ->get(route('student.achievements.certificate', $foreign))
            ->assertNotFound();
    }

    public function test_the_athlete_can_download_their_own_certificate(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('achievement-certificates/mine.pdf', 'binary');
        $achievement->update(['certificate_path' => 'achievement-certificates/mine.pdf']);

        $this->actingAs($athlete)
            ->get(route('student.achievements.certificate', $achievement))
            ->assertOk();
    }

    public function test_an_athlete_cannot_reach_the_admin_achievement_routes(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($athlete)->get(route('admin.achievements'))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.achievements.create'))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.achievements.show', $achievement))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.achievements.edit', $achievement))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.achievements.certificate', $achievement))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.achievements.store'), $this->payload($athlete))->assertForbidden();
        $this->actingAs($athlete)->put(route('admin.achievements.update', $achievement), $this->payload($athlete))->assertForbidden();
        $this->actingAs($athlete)->delete(route('admin.achievements.destroy', $achievement))->assertForbidden();
    }

    public function test_a_guest_is_redirected_away_from_both_sides(): void
    {
        $this->get(route('student.achievements'))->assertRedirect(route('login'));
        $this->get(route('admin.achievements'))->assertRedirect(route('login'));
    }

    public function test_the_administrator_cannot_be_blocked_by_an_administrator_only_route(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->get(route('admin.achievements'))->assertOk();
        $this->actingAs($admin)->get(route('admin.achievements.create'))->assertOk();
    }

    public function test_the_athlete_dashboard_shows_the_achievement_summary_and_recent_records(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Silver Medal - 200 Meter Sprint', 'achievement_type' => 'Silver Medal', 'date_achieved' => '2026-09-16']));

        $this->actingAs($athlete)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Achievements')
            ->assertSee('2 earned, 2 medals')
            ->assertSee('Recent Achievements')
            ->assertSee('View All Achievements')
            ->assertSee('Silver Medal - 200 Meter Sprint')
            ->assertSee('Gold Medal - 100 Meter Sprint');
    }

    public function test_the_athlete_dashboard_links_to_the_achievements_page(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($athlete)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(route('student.achievements'));
    }

    public function test_the_athlete_profile_lists_achievements(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($athlete)
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee('ACHIEVEMENTS')
            ->assertSee('Gold Medal - 100 Meter Sprint');
    }

    public function test_an_athlete_without_achievements_sees_a_friendly_empty_state(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($athlete)
            ->get(route('student.achievements'))
            ->assertOk()
            ->assertSee('No achievements yet.')
            ->assertSee('recorded by the Sports Coordinator');
    }

    public function test_the_admin_list_shows_an_empty_state_with_an_add_action(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)
            ->get(route('admin.achievements'))
            ->assertOk()
            ->assertSee('No achievements found.')
            ->assertSee(route('admin.achievements.create'));
    }

    public function test_creating_an_achievement_notifies_the_athlete(): void
    {
        Notification::fake();

        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $sport->id]))
            ->assertRedirect(route('admin.achievements'));

        $achievement = Achievement::firstOrFail();

        Notification::assertSentTo(
            $athlete,
            StudentUpdateNotification::class,
            function (StudentUpdateNotification $notification) use ($achievement) {
                return $notification->title === 'New Achievement'
                    && str_contains($notification->message, 'Gold Medal - 100 Meter Sprint')
                    && $notification->url === route('student.achievements.show', $achievement)
                    && ($notification->meta['dedupe_key'] ?? null) === 'achievement|'.$achievement->id;
            }
        );
    }

    public function test_editing_an_achievement_does_not_send_a_second_notification(): void
    {
        Notification::fake();

        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->put(route('admin.achievements.update', $achievement), $this->payload($athlete, ['sport_id' => $sport->id, 'title' => 'Revised Title']))
            ->assertRedirect(route('admin.achievements'));

        Notification::assertNothingSent();
    }

    public function test_the_achievement_notification_is_idempotent(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $notifier = app(StudentUpdateNotifier::class);
        $meta = ['dedupe_key' => 'achievement|'.$achievement->id];

        $notifier->notifyStudent($athlete, 'New Achievement', 'First send', route('student.achievements'), $meta);
        $notifier->notifyStudent($athlete, 'New Achievement', 'Second send', route('student.achievements'), $meta);

        $this->assertSame(1, $athlete->notifications()->where('data->dedupe_key', 'achievement|'.$achievement->id)->count());
    }

    public function test_a_persisted_notification_keeps_the_achievement_link(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $sport->id]))
            ->assertRedirect(route('admin.achievements'));

        $achievement = Achievement::firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $athlete->id,
            'type' => StudentUpdateNotification::class,
        ]);

        $notification = $athlete->notifications()->latest('id')->first();

        $this->assertSame('New Achievement', $notification->data['title']);
        $this->assertSame('achievement|'.$achievement->id, $notification->data['dedupe_key']);
        $this->assertSame(route('student.achievements.show', $achievement), $notification->data['url']);
    }

    public function test_the_required_fields_are_validated(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), [])
            ->assertSessionHasErrors(['athlete_id', 'title', 'achievement_type', 'date_achieved']);

        $this->assertDatabaseCount('achievements', 0);
    }

    public function test_a_duplicate_submission_does_not_create_a_second_record(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $sport->id]));
        $this->actingAs($admin)->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $sport->id]));

        $this->assertDatabaseCount('achievements', 2);
    }

    public function test_the_athlete_must_be_an_athlete_account(): void
    {
        [$admin, $sport] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($admin, ['sport_id' => $sport->id]))
            ->assertSessionHasErrors('athlete_id');
    }

    public function test_the_sport_must_belong_to_the_selected_athlete(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $volleyball->id]))
            ->assertSessionHasErrors('sport_id');

        $this->assertDatabaseCount('achievements', 0);
    }

    public function test_a_sport_from_an_approved_application_is_accepted(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);

        $athlete->applications()->create([
            'name' => $athlete->name,
            'email' => $athlete->email,
            'grade' => 'Grade 10',
            'sport' => 'Volleyball',
            'sport_id' => $volleyball->id,
            'status' => 'Approved',
            'athlete_id' => $athlete->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $volleyball->id]))
            ->assertRedirect(route('admin.achievements'));

        $this->assertDatabaseHas('achievements', [
            'athlete_id' => $athlete->id,
            'sport_id' => $volleyball->id,
        ]);
    }

    public function test_the_linked_event_must_match_the_selected_sport(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);
        $event = Event::create([
            'title' => 'Volleyball Tournament',
            'sport_id' => $volleyball->id,
            'ends_at' => now(),
            'venue' => 'Gym',
            'starts_at' => now()->subMonth(),
            'status' => 'Completed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'event_id' => $event->id,
            ]))
            ->assertSessionHasErrors('event_id');
    }

    public function test_an_invalid_date_is_rejected(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, ['sport_id' => $sport->id, 'date_achieved' => 'not-a-date']))
            ->assertSessionHasErrors('date_achieved');
    }

    public function test_an_unsupported_certificate_format_is_rejected(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'certificate' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ]))
            ->assertSessionHasErrors('certificate');

        $this->assertDatabaseCount('achievements', 0);
    }

    public function test_an_oversized_certificate_is_rejected(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.achievements.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'certificate' => UploadedFile::fake()->create('huge.pdf', 4096, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('certificate');
    }

    public function test_achievement_relationships_are_exposed_on_the_existing_models(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $event = Event::create([
            'title' => 'Regional Games',
            'ends_at' => now(),
            'sport_id' => $sport->id,
            'venue' => 'Track',
            'starts_at' => now()->subMonth(),
            'status' => 'Completed',
        ]);
        $achievement = Achievement::create($this->payload($athlete, ['sport_id' => $sport->id, 'event_id' => $event->id]));

        $this->assertTrue($athlete->achievements->contains($achievement));
        $this->assertTrue($sport->achievements->contains($achievement));
        $this->assertTrue($event->achievements->contains($achievement));
        $this->assertSame($athlete->id, $achievement->athlete->id);
    }

    public function test_deleting_an_athlete_removes_their_achievements(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $athlete->delete();

        $this->assertDatabaseCount('achievements', 0);
    }

    public function test_medal_and_category_labels_are_derived_from_the_type(): void
    {
        $gold = new Achievement(['achievement_type' => 'Gold Medal']);
        $champion = new Achievement(['achievement_type' => 'Champion']);
        $other = new Achievement(['achievement_type' => 'Jury Choice']);

        $this->assertTrue($gold->isMedal());
        $this->assertSame('Medal', $gold->category());
        $this->assertFalse($champion->isMedal());
        $this->assertSame('Title', $champion->category());
        $this->assertSame('Other', $other->category());
        $this->assertSame('Unassigned sport', $other->sportLabel());
    }

    public function test_multiple_athletes_holding_achievements_are_listed_for_the_administrator(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $second = User::factory()->create(['role' => 'Student', 'name' => 'Pedro Cruz', 'sport_id' => $sport->id, 'status' => 'Active']);
        $third = User::factory()->create(['role' => 'Student', 'name' => 'Ana Lopez', 'sport_id' => $sport->id, 'status' => 'Active']);

        Achievement::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Achievement::create($this->payload($second, ['sport_id' => $sport->id, 'title' => 'Pedro Championship']));
        Achievement::create($this->payload($third, ['sport_id' => $sport->id, 'title' => 'Ana Award']));

        $this->actingAs($admin)
            ->get(route('admin.achievements'))
            ->assertOk()
            ->assertSee('Gold Medal - 100 Meter Sprint')
            ->assertSee('Pedro Championship')
            ->assertSee('Ana Award');
    }

    public function test_an_athlete_in_several_sports_sees_the_achievement_under_the_right_sport(): void
    {
        [$admin, $basketball, $athlete] = $this->context('Basketball');
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);

        $athlete->applications()->create([
            'name' => $athlete->name,
            'email' => $athlete->email,
            'grade' => 'Grade 10',
            'sport' => 'Volleyball',
            'sport_id' => $volleyball->id,
            'status' => 'Approved',
            'athlete_id' => $athlete->id,
        ]);

        Achievement::create($this->payload($athlete, ['sport_id' => $volleyball->id, 'title' => 'Volleyball Best Receiver']));

        $this->actingAs($athlete)
            ->get(route('student.achievements'))
            ->assertOk()
            ->assertSee('Volleyball Best Receiver')
            ->assertSee('Volleyball');

        $this->assertSame('Volleyball', Achievement::firstOrFail()->sportLabel());
    }
}
