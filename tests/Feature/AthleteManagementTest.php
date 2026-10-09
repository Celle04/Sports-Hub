<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Event;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AthleteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_filter_and_view_an_athlete_profile(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        Coach::create(['name' => 'Coach Santos', 'email' => 'coach@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id]);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'student_id' => '2026-0001', 'sport_id' => $sport->id]);
        $application = Application::create(['name' => $athlete->name, 'student_id' => $athlete->student_id, 'grade' => 'Grade 10', 'gender' => 'Male', 'email' => $athlete->email, 'sport' => $sport->name, 'sport_id' => $sport->id, 'athlete_id' => $athlete->id, 'status' => 'Approved']);
        MedicalRecord::create(['user_id' => $athlete->id, 'athlete_id' => $athlete->id, 'medical_status' => 'Cleared', 'examination_date' => today()]);
        $event = Event::create(['title' => 'Basketball Practice', 'sport_id' => $sport->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'venue' => 'Main Court', 'status' => 'Scheduled']);
        Attendance::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'Present', 'attended_on' => today()]);

        $this->actingAs($admin)->get(route('athletes.index', ['search' => 'Juan', 'eligibility' => 'Eligible']))
            ->assertOk()
            ->assertSee('Juan Dela Cruz');
        $this->actingAs($admin)->get(route('athletes.show', $athlete))
            ->assertOk()
            ->assertSee('Basketball Practice')
            ->assertSee('Coach Santos')
            ->assertSee('Eligible');
    }

    public function test_administrator_can_record_an_emergency_contact_for_an_athlete(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name,
                'email' => $athlete->email,
                'status' => 'Active',
                'grade_level' => 'Grade 11 - St. Andrew',
                'emergency_contact_name' => 'Maria Dela Cruz',
                'emergency_contact_relationship' => 'Mother',
                'emergency_contact_phone' => '09179876543',
            ])
            ->assertRedirect(route('athletes.show', $athlete));

        $athlete->refresh();

        $this->assertSame('Grade 11 - St. Andrew', $athlete->grade_level);
        $this->assertSame('Maria Dela Cruz', $athlete->emergency_contact_name);
        $this->assertSame('Mother', $athlete->emergency_contact_relationship);
        $this->assertSame('09179876543', $athlete->emergency_contact_phone);

        $this->actingAs($admin)->get(route('athletes.show', $athlete))
            ->assertOk()
            ->assertSee('Emergency Contact')
            ->assertSee('Maria Dela Cruz')
            ->assertSee('09179876543');
    }

    public function test_administrator_emergency_contact_still_requires_a_name_and_a_number(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name,
                'email' => $athlete->email,
                'status' => 'Active',
                'emergency_contact_phone' => '09179876543',
            ])
            ->assertSessionHasErrors('emergency_contact_name');

        $this->assertNull($athlete->fresh()->emergency_contact_phone);
    }

    public function test_administrator_athlete_page_warns_when_no_emergency_contact_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);

        $this->actingAs($admin)->get(route('athletes.show', $athlete))
            ->assertOk()
            ->assertSee('No usable emergency contact recorded.');
    }

    public function test_related_athlete_is_deactivated_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);
        Application::create(['name' => $athlete->name, 'student_id' => $athlete->student_id, 'grade' => 'Grade 10', 'gender' => 'Male', 'email' => $athlete->email, 'sport' => 'General', 'athlete_id' => $athlete->id, 'status' => 'Pending']);

        $this->actingAs($admin)->delete(route('athletes.destroy', $athlete));

        $this->assertDatabaseHas('users', ['id' => $athlete->id, 'status' => 'Inactive']);
    }

    public function test_administrator_edit_form_posts_the_photo_as_multipart(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);

        // Without the multipart enctype the browser silently sends no file.
        $this->actingAs($admin)->get(route('athletes.edit', $athlete))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="profile_photo"', false)
            ->assertSee('data-photo-input', false);
    }

    public function test_administrator_can_upload_a_profile_photo_for_an_athlete(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name,
                'email' => $athlete->email,
                'status' => 'Active',
                'profile_photo' => $this->photo('avatar.jpg'),
            ])
            ->assertRedirect(route('athletes.show', $athlete))
            ->assertSessionHasNoErrors();

        $athlete->refresh();

        $this->assertNotNull($athlete->profile_photo_path);
        Storage::disk('public')->assertExists($athlete->profile_photo_path);

        // The athlete detail page shows the photo instead of the initials.
        $this->actingAs($admin)->get(route('athletes.show', $athlete))
            ->assertOk()
            ->assertSee($athlete->profile_photo_path, false)
            ->assertDontSee('athlete-avatar-initials', false);
    }

    public function test_administrator_upload_replaces_the_previous_photo_and_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'profile_photo' => $this->photo('first.jpg'),
        ]);

        $firstPhoto = $athlete->fresh()->profile_photo_path;
        $this->assertNotNull($firstPhoto);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'profile_photo' => $this->photo('second.jpg'),
        ]);

        $secondPhoto = $athlete->fresh()->profile_photo_path;

        $this->assertNotSame($firstPhoto, $secondPhoto);
        Storage::disk('public')->assertExists($secondPhoto);
        Storage::disk('public')->assertMissing($firstPhoto);
    }

    public function test_administrator_can_remove_an_athlete_profile_photo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'profile_photo' => $this->photo('avatar.jpg'),
        ]);

        $storedPhoto = $athlete->fresh()->profile_photo_path;

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
                'remove_photo' => '1',
            ])
            ->assertRedirect(route('athletes.show', $athlete));

        $this->assertNull($athlete->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($storedPhoto);
    }

    public function test_uploading_a_photo_wins_over_the_remove_checkbox(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'profile_photo' => $this->photo('avatar.jpg'),
            'remove_photo' => '1',
        ]);

        $this->assertNotNull($athlete->fresh()->profile_photo_path);
    }

    public function test_administrator_photo_upload_rejects_oversized_and_non_image_files(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
                'profile_photo' => UploadedFile::fake()->createWithContent('huge.jpg', $this->pngBytes().str_repeat('0', 3 * 1024 * 1024)),
            ])
            ->assertSessionHasErrors('profile_photo', 'The profile photo may not be larger than 2 MB.');

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
                'profile_photo' => UploadedFile::fake()->create('resume.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('profile_photo');

        $this->assertNull($athlete->fresh()->profile_photo_path);
    }

    public function test_an_oversized_photo_leaves_the_existing_photo_in_place(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'profile_photo' => $this->photo('avatar.jpg'),
        ]);

        $originalPhoto = $athlete->fresh()->profile_photo_path;

        $this->actingAs($admin)
            ->from(route('athletes.edit', $athlete))
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
                'profile_photo' => UploadedFile::fake()->createWithContent('huge.jpg', $this->pngBytes().str_repeat('0', 3 * 1024 * 1024)),
            ])
            ->assertSessionHasErrors('profile_photo');

        $this->assertSame($originalPhoto, $athlete->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists($originalPhoto);
    }

    public function test_a_shared_default_photo_path_is_never_deleted(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);
        Storage::disk('public')->put('avatars/default-athlete.png', 'shared');
        $athlete->update(['profile_photo_path' => 'avatars/default-athlete.png']);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'remove_photo' => '1',
        ]);

        Storage::disk('public')->assertExists('avatars/default-athlete.png');
        $this->assertNull($athlete->fresh()->profile_photo_path);
    }

    public function test_an_athlete_cannot_change_another_athletes_photo(): void
    {
        Storage::fake('public');

        $otherAthlete = User::factory()->create(['role' => 'Student']);
        $athlete = User::factory()->create(['role' => 'Student', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($otherAthlete)
            ->put(route('athletes.update', $athlete), [
                'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
                'profile_photo' => $this->photo('avatar.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull($athlete->fresh()->profile_photo_path);
    }

    public function test_the_portal_layout_loads_the_profile_script(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);

        // Without this script the photo preview never updates.
        $this->actingAs($admin)->get(route('athletes.edit', $athlete))
            ->assertOk()
            ->assertSee('js/profile.js', false);
    }

    public function test_administrator_listing_shows_the_athlete_photo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($admin)->put(route('athletes.update', $athlete), [
            'name' => $athlete->name, 'email' => $athlete->email, 'status' => 'Active',
            'profile_photo' => $this->photo('avatar.jpg'),
        ]);

        $path = $athlete->fresh()->profile_photo_path;

        $this->actingAs($admin)->get(route('athletes.index'))
            ->assertOk()
            ->assertSee($path, false)
            ->assertSee('athlete-avatar-sm', false)
            ->assertDontSee('loading=&quot;lazy&quot;', false);
    }

    public function test_administrator_listing_falls_back_to_initials_without_a_photo(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('athletes.index'))->assertOk();

        // Two initials from the first and last name, not the first two letters.
        $this->assertStringContainsString('>JC</span>', $response->getContent());
        $this->assertStringNotContainsString('athlete-avatar-sm" src', $response->getContent());
    }

    public function test_student_dashboard_avatar_falls_back_to_name_initials(): void
    {
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => 'Active']);

        $content = $this->actingAs($athlete)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('>JC</span>', $content);
        $this->assertStringNotContainsString('>JU<', $content);
    }

    public function test_student_dashboard_avatar_uses_the_public_profile_photo_url(): void
    {
        Storage::fake('public');

        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => 'Active']);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name, 'email' => $athlete->email,
            'profile_photo' => $this->photo('avatar.jpg'),
        ]);

        $expected = $athlete->fresh()->profilePhotoUrl();

        $this->assertNotNull($expected);
        $this->assertStringContainsString('src="'.e($expected).'"', $this->actingAs($athlete)->get(route('student.dashboard'))->getContent());
    }

    /**
     * The uploaded photo must show up in the circular avatar on every screen the
     * athlete sees, all from the one users.profile_photo_path value.
     */
public function test_uploaded_photo_appears_in_the_sidebar_avatar_on_every_athlete_page(): void
    {
        Storage::fake('public');

        $athlete = User::factory()->create([
            'role' => 'Student', 'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com', 'status' => 'Active',
        ]);

        // Test 1 - upload from the profile page.
        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name, 'email' => $athlete->email,
            'profile_photo' => $this->photo('avatar.jpg'),
        ]);

        $path = $athlete->fresh()->profile_photo_path;
        $this->assertNotNull($path, 'The upload should have been stored.');
        $expectedUrl = e($athlete->fresh()->profilePhotoUrl());

        // Test 2 - dashboard. Test 4 - a fresh request (simulates a refresh).
        // Test 5 - logging out and back in first.
        $this->post(route('logout'));
        $dashboard = $this->actingAs($athlete)->get(route('student.dashboard'));

        $this->assertStringContainsString('src="'.$expectedUrl.'"', $dashboard->getContent());
        $this->assertStringContainsString('user-chip-avatar', $dashboard->getContent());

        // The profile page uses the same stored image.
        $profile = $this->actingAs($athlete)->get(route('student.profile'));
        $this->assertStringContainsString('src="'.$expectedUrl.'"', $profile->getContent());

        // Every athlete page carries the sidebar avatar.
        foreach (['student.dashboard', 'student.profile', 'student.calendar', 'student.attendance'] as $route) {
            $this->actingAs($athlete)->get(route($route))
                ->assertOk()
                ->assertSee($path, false);
        }
    }

    public function test_sidebar_avatar_uses_initials_when_the_athlete_has_no_photo(): void
    {
        $athlete = User::factory()->create([
            'role' => 'Student', 'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com', 'status' => 'Active',
        ]);

        $content = $this->actingAs($athlete)->get(route('student.dashboard'))->assertOk()->getContent();

        // Test 7 - a default avatar, never a broken image.
        $this->assertStringContainsString('user-chip-avatar', $content);
        $this->assertStringNotContainsString('profile-photos/', $content);
    }

    public function test_sidebar_avatar_never_shows_another_users_photo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator', 'name' => 'Admin User']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'status' => 'Active']);

        // Give the admin a photo directly, then confirm the athlete never sees it.
        Storage::disk('public')->put('profile-photos/admin-photo.jpg', 'x');
        $admin->forceFill(['profile_photo_path' => 'profile-photos/admin-photo.jpg'])->save();

        $athleteContent = $this->actingAs($athlete)->get(route('student.dashboard'))->getContent();
        $this->assertStringNotContainsString('admin-photo.jpg', $athleteContent);
        $this->assertStringContainsString('user-chip-avatar', $athleteContent);
    }

    public function test_replacing_a_photo_changes_the_url_shown_in_the_sidebar(): void
    {
        Storage::fake('public');

        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'status' => 'Active']);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name, 'email' => $athlete->email,
            'profile_photo' => $this->photo('first.jpg'),
        ]);

        $firstPath = $athlete->fresh()->profile_photo_path;

        // Test 6 - replace the image.
        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name, 'email' => $athlete->email,
            'profile_photo' => $this->photo('second.jpg'),
        ]);

        $secondPath = $athlete->fresh()->profile_photo_path;

        // The stored file name is random, so the URL changes and the browser
        // cannot serve a stale cached copy. No cache busting needed.
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertExists($secondPath);
        Storage::disk('public')->assertMissing($firstPath);

        $content = $this->actingAs($athlete)->get(route('student.dashboard'))->getContent();
        $this->assertStringContainsString($secondPath, $content);
        $this->assertStringNotContainsString($firstPath, $content);
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
    }

    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes());
    }
}
