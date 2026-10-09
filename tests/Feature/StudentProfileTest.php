<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');
    }

    public function test_athlete_can_open_their_profile_with_their_own_information(): void
    {
        $athlete = $this->athlete(['name' => 'Juan Dela Cruz', 'student_id' => '2026-00123']);
        $athlete->sport()->associate($this->sport())->save();

        $this->actingAs($athlete)
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('2026-00123')
            ->assertSee('Basketball')
            ->assertSee('Athlete')
            ->assertSee('Change Password')
            ->assertSee('Save Changes');
    }

    public function test_profile_shows_initials_when_no_photo_is_uploaded(): void
    {
        $this->actingAs($this->athlete(['name' => 'Juan Dela Cruz']))
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee('JC')
            ->assertDontSee('class="student-profile-avatar"', false);
    }

    public function test_athlete_can_update_allowed_information(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => 'Juan Dela Cruz Jr.',
                'email' => 'juan.dc@example.com',
                'phone' => '09171234567',
            ])
            ->assertRedirect(route('student.profile'))
            ->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $athlete->id,
            'name' => 'Juan Dela Cruz Jr.',
            'email' => 'juan.dc@example.com',
            'phone' => '09171234567',
        ]);
    }

    public function test_updates_persist_after_refresh(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => 'Juan Dela Cruz Jr.',
            'email' => 'juan.dc@example.com',
        ]);

        $this->actingAs($athlete->fresh())
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz Jr.')
            ->assertSee('juan.dc@example.com');
    }

    public function test_admin_controlled_fields_cannot_be_changed_from_the_profile(): void
    {
        $sport = $this->sport();
        $athlete = $this->athlete(['sport_id' => $sport->id, 'student_id' => '2026-00123', 'role' => 'Student']);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => 'Juan Dela Cruz',
            'email' => $athlete->email,
            'sport_id' => null,
            'student_id' => 'HACKED-1',
            'role' => 'Administrator',
        ]);

        $athlete->refresh();

        $this->assertSame($sport->id, $athlete->sport_id);
        $this->assertSame('2026-00123', $athlete->student_id);
        $this->assertSame('Student', $athlete->role);
    }

    public function test_email_is_required_and_must_be_valid(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), ['name' => 'Juan', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->assertSame($athlete->email, $athlete->fresh()->email);
    }

    public function test_email_must_stay_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), ['name' => 'Juan', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email', 'The email has already been taken.');

        $this->assertDatabaseMissing('users', ['email' => 'taken@example.com', 'id' => $athlete->id]);
    }

    public function test_athlete_can_upload_a_profile_photo(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => 'Juan Dela Cruz',
                'email' => $athlete->email,
                'profile_photo' => $this->fakePhoto('juan.jpg'),
            ])
            ->assertRedirect(route('student.profile'));

        $path = $athlete->fresh()->profile_photo_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('profile-photos/', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($athlete->fresh())
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_replacing_a_photo_deletes_the_previous_file(): void
    {
        $athlete = $this->athlete();
        Storage::disk('public')->put('profile-photos/old-photo.png', 'old');

        $athlete->update(['profile_photo_path' => 'profile-photos/old-photo.png']);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => 'Juan Dela Cruz',
            'email' => $athlete->email,
            'profile_photo' => $this->fakePhoto('new-photo.png'),
        ]);

        Storage::disk('public')->assertMissing('profile-photos/old-photo.png');
        Storage::disk('public')->assertExists($athlete->fresh()->profile_photo_path);
    }

    public function test_photo_removal_does_not_delete_shared_default_images(): void
    {
        $athlete = $this->athlete();
        Storage::disk('public')->put('avatars/default-athlete.png', 'default');
        $athlete->update(['profile_photo_path' => 'avatars/default-athlete.png']);

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => 'Juan Dela Cruz',
                'email' => $athlete->email,
                'remove_photo' => '1',
            ])
            ->assertRedirect(route('student.profile'));

        $this->assertNull($athlete->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists('avatars/default-athlete.png');
    }

    public function test_athlete_can_remove_their_own_uploaded_photo(): void
    {
        $athlete = $this->athlete();
        Storage::disk('public')->put('profile-photos/juan.png', 'photo');
        $athlete->update(['profile_photo_path' => 'profile-photos/juan.png']);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => 'Juan Dela Cruz',
            'email' => $athlete->email,
            'remove_photo' => '1',
        ]);

        $this->assertNull($athlete->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing('profile-photos/juan.png');
    }

    public function test_unsupported_file_types_are_rejected(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => 'Juan Dela Cruz',
                'email' => $athlete->email,
                'profile_photo' => UploadedFile::fake()->create('resume.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('profile_photo');

        $this->assertNull($athlete->fresh()->profile_photo_path);
    }

    public function test_oversized_photos_are_rejected(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => 'Juan Dela Cruz',
                'email' => $athlete->email,
                'profile_photo' => $this->fakePhoto('huge.png', 3 * 1024),
            ])
            ->assertSessionHasErrors('profile_photo', 'The profile photo may not be larger than 2 MB.');

        $this->assertNull($athlete->fresh()->profile_photo_path);
    }

    public function test_athlete_can_change_their_password_with_the_current_one(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.password'), [
                'current_password' => 'old-password',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertRedirect(route('student.profile'))
            ->assertSessionHas('success', 'Your password has been changed successfully.');

        $this->assertTrue(Hash::check('new-password-1', $athlete->fresh()->password));
    }

    public function test_the_new_password_works_on_login_and_the_old_one_stops_working(): void
    {
        $athlete = $this->athlete(['email' => 'juan@example.com']);

        $this->actingAs($athlete)->patch(route('student.profile.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $this->post(route('logout'));

        $this->post(route('login.submit'), ['role' => 'Student', 'username' => 'juan@example.com', 'password' => 'old-password'])
            ->assertRedirect(route('login'));

        $this->post(route('login.submit'), ['role' => 'Student', 'username' => 'juan@example.com', 'password' => 'new-password-1'])
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_an_incorrect_current_password_is_rejected(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertSessionHasErrors('current_password', 'The current password is incorrect.');

        $this->assertTrue(Hash::check('old-password', $athlete->fresh()->password));
    }

    public function test_new_password_confirmation_must_match(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.password'), [
                'current_password' => 'old-password',
                'password' => 'new-password-1',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors('password', 'The new passwords do not match.');

        $this->assertTrue(Hash::check('old-password', $athlete->fresh()->password));
    }

    public function test_short_passwords_are_rejected(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.password'), [
                'current_password' => 'old-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $athlete->fresh()->password));
    }

    public function test_passwords_are_never_stored_in_plain_text(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)->patch(route('student.profile.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $this->assertDatabaseMissing('users', ['password' => 'new-password-1']);
        $this->assertStringNotContainsString('new-password-1', (string) $athlete->fresh()->password);
    }

    public function test_admins_cannot_use_the_athlete_profile_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->patch(route('student.profile.update'), [
            'name' => 'Hijacked',
            'email' => $admin->email,
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('student.profile.password'), [
            'current_password' => 'password',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertForbidden();
    }

    public function test_guests_are_redirected_from_the_profile(): void
    {
        $this->get(route('student.profile'))->assertRedirect(route('login'));
    }

    public function test_an_athlete_cannot_target_another_user_through_the_form(): void
    {
        $other = User::factory()->create(['role' => 'Student', 'email' => 'other@example.com', 'name' => 'Other Athlete']);
        $athlete = $this->athlete();

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => 'Only My Own Name',
            'email' => 'mine@example.com',
            'id' => $other->id,
            'user_id' => $other->id,
            'athlete_id' => $other->id,
        ]);

        $other->refresh();

        $this->assertSame('Other Athlete', $other->name);
        $this->assertSame('other@example.com', $other->email);
    }

    public function test_athlete_can_save_their_grade_level_and_emergency_contact(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => $athlete->name,
                'email' => $athlete->email,
                'grade_level' => 'Grade 11 - St. Andrew',
                'emergency_contact_name' => 'Maria Dela Cruz',
                'emergency_contact_relationship' => 'Mother',
                'emergency_contact_phone' => '09179876543',
            ])
            ->assertRedirect(route('student.profile'));

        $athlete->refresh();

        $this->assertSame('Grade 11 - St. Andrew', $athlete->grade_level);
        $this->assertSame('Maria Dela Cruz', $athlete->emergency_contact_name);
        $this->assertSame('Mother', $athlete->emergency_contact_relationship);
        $this->assertSame('09179876543', $athlete->emergency_contact_phone);
        $this->assertTrue($athlete->hasEmergencyContact());
    }

    public function test_profile_displays_the_saved_grade_level_and_emergency_contact(): void
    {
        $athlete = $this->athlete([
            'grade_level' => 'Grade 11 - St. Andrew',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_relationship' => 'Mother',
            'emergency_contact_phone' => '09179876543',
        ]);

        $this->actingAs($athlete)->get(route('student.profile'))
            ->assertOk()
            ->assertSee('Grade level')
            ->assertSee('EMERGENCY CONTACT')
            ->assertSee('Who to contact in an emergency')
            ->assertSee('Grade 11 - St. Andrew')
            ->assertSee('Maria Dela Cruz')
            ->assertSee('09179876543');
    }

    /**
     * An athlete who applied before the grade became editable still gets a
     * grade level, taken from their most recent application.
     */
    public function test_grade_level_falls_back_to_the_application_grade(): void
    {
        $athlete = $this->athlete();
        Application::create([
            'name' => $athlete->name,
            'student_id' => $athlete->student_id,
            'grade' => 'Grade 9 - St. Luke',
            'email' => $athlete->email,
            'sport' => 'Basketball',
            'athlete_id' => $athlete->id,
            'status' => 'Approved',
        ]);

        $athlete->refresh();

        $this->assertNull($athlete->grade_level);
        $this->assertSame('Grade 9 - St. Luke', $athlete->gradeLevel());

        $this->actingAs($athlete)->get(route('student.profile'))
            ->assertOk()
            ->assertSee('Grade 9 - St. Luke')
            ->assertSee('From your application: Grade 9 - St. Luke');
    }

    public function test_a_grade_level_typed_by_the_athlete_wins_over_the_application_grade(): void
    {
        $athlete = $this->athlete();
        Application::create([
            'name' => $athlete->name,
            'student_id' => $athlete->student_id,
            'grade' => 'Grade 9 - St. Luke',
            'email' => $athlete->email,
            'sport' => 'Basketball',
            'athlete_id' => $athlete->id,
            'status' => 'Approved',
        ]);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name,
            'email' => $athlete->email,
            'grade_level' => 'Grade 10 - St. Andrew',
        ]);

        $this->assertSame('Grade 10 - St. Andrew', $athlete->fresh()->gradeLevel());
    }

    /**
     * A phone number with no name is useless in an emergency, so the pair is
     * validated together instead of saving a half filled contact.
     */
    public function test_emergency_contact_requires_both_a_name_and_a_number(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => $athlete->name,
                'email' => $athlete->email,
                'emergency_contact_name' => 'Maria Dela Cruz',
            ])
            ->assertSessionHasErrors('emergency_contact_phone');

        $this->actingAs($athlete)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => $athlete->name,
                'email' => $athlete->email,
                'emergency_contact_phone' => '09179876543',
            ])
            ->assertSessionHasErrors('emergency_contact_name');

        $this->assertNull($athlete->fresh()->emergency_contact_name);
        $this->assertNull($athlete->fresh()->emergency_contact_phone);
        $this->assertFalse($athlete->fresh()->hasEmergencyContact());
    }

    public function test_relationship_is_optional_but_the_pair_is_still_required(): void
    {
        $athlete = $this->athlete();

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name,
            'email' => $athlete->email,
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_relationship' => '',
            'emergency_contact_phone' => '09179876543',
        ])->assertSessionHasNoErrors();

        $athlete->refresh();

        $this->assertSame('Maria Dela Cruz', $athlete->emergency_contact_name);
        $this->assertNull($athlete->emergency_contact_relationship);
    }

    public function test_emergency_contact_can_be_cleared_again(): void
    {
        $athlete = $this->athlete([
            'grade_level' => 'Grade 11',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_relationship' => 'Mother',
            'emergency_contact_phone' => '09179876543',
        ]);

        $this->actingAs($athlete)->patch(route('student.profile.update'), [
            'name' => $athlete->name,
            'email' => $athlete->email,
            'grade_level' => '',
            'emergency_contact_name' => '',
            'emergency_contact_relationship' => '',
            'emergency_contact_phone' => '',
        ])->assertSessionHasNoErrors();

        $athlete->refresh();

        $this->assertNull($athlete->grade_level);
        $this->assertNull($athlete->emergency_contact_name);
        $this->assertNull($athlete->emergency_contact_relationship);
        $this->assertNull($athlete->emergency_contact_phone);
    }

    /**
     * A real PNG built from bytes, so the image tests do not depend on GD.
     */
    private function fakePhoto(string $name, int $paddingKilobytes = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png.str_repeat('0', $paddingKilobytes * 1024));
    }

    private function athlete(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'Student',
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'status' => 'Active',
            'password' => 'old-password',
        ], $attributes));
    }

    private function sport(): Sport
    {
        return Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
    }
}