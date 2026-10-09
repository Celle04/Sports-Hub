<?php

namespace Tests\Feature;

use App\Models\MedicalIncident;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('private');
    }

    /**
     * @return array{0: User, 1: Sport, 2: User}
     */
    private function context(string $sportName = 'Basketball'): array
    {
        $sport = Sport::create(['name' => $sportName, 'classification' => 'Team Sport', 'description' => 'Court sport']);
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
            'examination_date' => today()->subDays(7)->format('Y-m-d'),
            'medical_status' => 'Cleared',
            'next_checkup_date' => today()->addMonths(6)->format('Y-m-d'),
            'findings' => 'Healthy and fit for competition.',
            'restrictions' => 'None',
            'notes' => 'Cleared for school sports activities.',
        ], $overrides);
    }

    public function test_a_guest_is_redirected_away_from_the_medical_module(): void
    {
        $this->get(route('admin.medical'))->assertRedirect(route('login'));
        $this->get(route('admin.medical.create'))->assertRedirect(route('login'));
    }

    public function test_an_athlete_cannot_reach_any_medical_management_route(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('medical-certificates/cert.pdf', 'binary');
        $record->update(['medical_certificate' => 'medical-certificates/cert.pdf']);

        $this->actingAs($athlete)->get(route('admin.medical'))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.medical.create'))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.medical.show', $record))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.medical.edit', $record))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.medical.certificate', $record))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.medical.certificate.view', $record))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.medical.store'), $this->payload($athlete))->assertForbidden();
        $this->actingAs($athlete)->put(route('admin.medical.update', $record), $this->payload($athlete))->assertForbidden();
        $this->actingAs($athlete)->patch(route('admin.medical.archive', $record))->assertForbidden();
        $this->actingAs($athlete)->patch(route('admin.medical.restore', $record))->assertForbidden();
        $this->actingAs($athlete)->delete(route('admin.medical.destroy', $record))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.medical.incidents.store'), [])->assertForbidden();
    }

    public function test_an_administrator_can_create_a_medical_record_for_an_athlete(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Indoor court sport']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'sport_id' => $sport->id]);

        $this->actingAs($admin);

        $this->get(route('admin.medical'))->assertOk();

        $this->post(route('admin.medical.store'), [
            'athlete_id' => $athlete->id,
            'examination_date' => '2026-09-01',
            'medical_status' => 'Cleared',
            'next_checkup_date' => '2027-09-01',
            'findings' => 'Healthy and fit for competition.',
            'restrictions' => 'None',
            'notes' => 'Cleared for school sports activities.',
        ])->assertRedirect();

        $this->assertDatabaseHas('medical_records', [
            'athlete_id' => $athlete->id,
            'medical_status' => 'Cleared',
        ]);
    }

    public function test_the_created_record_is_listed_for_the_administrator(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->get(route('admin.medical'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('S-2048')
            ->assertSee('Basketball')
            ->assertSee('Cleared')
            ->assertSee(route('admin.medical.edit', $record))
            ->assertSee(route('admin.medical.show', $record));
    }

    public function test_the_summary_cards_are_rendered(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->get(route('admin.medical'))
            ->assertOk()
            ->assertSeeInOrder(['Total Records', 'Cleared', 'Pending', 'Restricted', 'Expiring Soon', 'Under Recovery']);
    }

    public function test_the_required_record_fields_are_validated(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.store'), [])
            ->assertSessionHasErrors(['athlete_id', 'examination_date', 'medical_status']);

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_the_athlete_must_be_a_student_account(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.store'), $this->payload($admin))
            ->assertSessionHasErrors('athlete_id');

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_the_next_checkup_date_cannot_predate_the_examination(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'examination_date' => '2026-10-10',
                'next_checkup_date' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('next_checkup_date');

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_an_unsupported_certificate_format_is_rejected(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'medical_certificate' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ]))
            ->assertSessionHasErrors('medical_certificate');

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_an_oversized_certificate_is_rejected(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'medical_certificate' => UploadedFile::fake()->create('huge.pdf', 4096, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('medical_certificate');

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_a_certificate_upload_is_stored_on_the_private_disk(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.store'), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'medical_certificate' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect(route('admin.medical'));

        $record = MedicalRecord::firstOrFail();

        $this->assertStringStartsWith('medical-certificates/', $record->medical_certificate);
        Storage::disk('private')->assertExists($record->medical_certificate);

        $this->actingAs($admin)
            ->get(route('admin.medical.certificate', $record))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.medical.certificate.view', $record))
            ->assertOk();
    }

    public function test_a_record_without_a_stored_certificate_returns_404(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('medical-certificates/x.pdf', 'binary');
        $record->update(['medical_certificate' => 'medical-certificates/gone.pdf']);

        $this->actingAs($admin)
            ->get(route('admin.medical.certificate', $record))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.medical.certificate.view', $record))
            ->assertNotFound();
    }

    public function test_replacing_a_certificate_removes_the_previous_file(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));
        Storage::disk('private')->put('medical-certificates/old.pdf', 'binary');
        $record->update(['medical_certificate' => 'medical-certificates/old.pdf']);

        $this->actingAs($admin)
            ->put(route('admin.medical.update', $record), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'medical_certificate' => UploadedFile::fake()->create('new.pdf', 90, 'application/pdf'),
            ]))
            ->assertRedirect(route('admin.medical'));

        Storage::disk('private')->assertMissing('medical-certificates/old.pdf');
        Storage::disk('private')->assertExists($record->fresh()->medical_certificate);
    }

    public function test_an_administrator_can_update_a_medical_record(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->put(route('admin.medical.update', $record), $this->payload($athlete, [
                'sport_id' => $sport->id,
                'medical_status' => 'Restricted',
                'next_checkup_date' => today()->addDays(10)->format('Y-m-d'),
            ]))
            ->assertRedirect(route('admin.medical'));

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'medical_status' => 'Restricted',
        ]);
    }

    public function test_an_administrator_can_view_the_athlete_medical_profile(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));
        MedicalIncident::create([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'incident_date' => '2026-08-20',
            'activity' => 'Training drill',
            'injury_type' => 'Ankle sprain',
            'body_part' => 'Right ankle',
            'severity' => 'Moderate',
            'description' => 'Rolled the ankle during a footwork drill.',
            'treatment' => 'RICE and rest.',
            'rest_period_days' => 7,
            'medical_clearance' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.medical.show', $record))
            ->assertOk()
            ->assertSee('ATHLETE MEDICAL PROFILE')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Healthy and fit for competition.')
            ->assertSee('None')
            ->assertSee('Cleared for school sports activities.')
            ->assertSee('Injury & Medical Incident History')
            ->assertSee('Ankle sprain')
            ->assertSee('Moderate');
    }

    public function test_an_administrator_can_archive_restore_and_delete_a_record(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $record = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));

        $this->actingAs($admin)
            ->patch(route('admin.medical.archive', $record))
            ->assertRedirect(route('admin.medical'));

        $this->assertNotNull($record->fresh()->archived_at);

        $this->actingAs($admin)
            ->get(route('admin.medical'))
            ->assertOk()
            ->assertSee('No medical records found.');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['archived' => 1]))
            ->assertOk()
            ->assertSee(route('admin.medical.edit', $record));

        $this->actingAs($admin)
            ->patch(route('admin.medical.restore', $record))
            ->assertRedirect(route('admin.medical'));

        $this->assertNull($record->fresh()->archived_at);

        Storage::disk('private')->put('medical-certificates/cert.pdf', 'binary');
        $record->update(['medical_certificate' => 'medical-certificates/cert.pdf']);

        $this->actingAs($admin)
            ->delete(route('admin.medical.destroy', $record))
            ->assertRedirect(route('admin.medical'));

        $this->assertDatabaseMissing('medical_records', ['id' => $record->id]);
        Storage::disk('private')->assertMissing('medical-certificates/cert.pdf');
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport']);
        $other = User::factory()->create(['role' => 'Student', 'name' => 'Maria Reyes', 'student_id' => 'S-3099', 'sport_id' => $volleyball->id, 'status' => 'Active']);

        MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id, 'examination_date' => '2026-09-25']));
        MedicalRecord::create($this->payload($other, ['sport_id' => $volleyball->id, 'medical_status' => 'Restricted', 'examination_date' => '2026-10-01']));

        $this->actingAs($admin)
            ->get(route('admin.medical', ['search' => 'Maria']))
            ->assertOk()
            ->assertSee('Maria Reyes')
            ->assertDontSee('Sep 25, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['search' => 'S-2048']))
            ->assertOk()
            ->assertSee('Sep 25, 2026')
            ->assertDontSee('Oct 1, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['sport_id' => $volleyball->id]))
            ->assertOk()
            ->assertSee('Oct 1, 2026')
            ->assertDontSee('Sep 25, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['status' => 'Restricted']))
            ->assertOk()
            ->assertSee('Oct 1, 2026')
            ->assertDontSee('Sep 25, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['examination_date' => '2026-09-25']))
            ->assertOk()
            ->assertSee('Sep 25, 2026')
            ->assertDontSee('Oct 1, 2026');
    }

    public function test_the_list_can_be_filtered_by_certificate_state(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $valid = MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id, 'examination_date' => '2026-09-26', 'next_checkup_date' => today()->addDays(60)->format('Y-m-d')]));
        Storage::disk('private')->put('medical-certificates/valid.pdf', 'binary');
        $valid->update(['medical_certificate' => 'medical-certificates/valid.pdf']);

        $other = User::factory()->create(['role' => 'Student', 'name' => 'Bob Santos', 'student_id' => 'S-3100', 'sport_id' => $sport->id, 'status' => 'Active']);
        $expiring = MedicalRecord::create($this->payload($other, ['sport_id' => $sport->id, 'examination_date' => '2026-09-27', 'next_checkup_date' => today()->addDays(5)->format('Y-m-d')]));
        Storage::disk('private')->put('medical-certificates/expiring.pdf', 'binary');
        $expiring->update(['medical_certificate' => 'medical-certificates/expiring.pdf']);

        $third = User::factory()->create(['role' => 'Student', 'name' => 'Carla Reyes', 'student_id' => 'S-3101', 'sport_id' => $sport->id, 'status' => 'Active']);
        $expired = MedicalRecord::create($this->payload($third, ['sport_id' => $sport->id, 'examination_date' => '2026-09-28', 'next_checkup_date' => today()->subDays(5)->format('Y-m-d')]));
        Storage::disk('private')->put('medical-certificates/expired.pdf', 'binary');
        $expired->update(['medical_certificate' => 'medical-certificates/expired.pdf']);

        $fourth = User::factory()->create(['role' => 'Student', 'name' => 'Diana Gomez', 'student_id' => 'S-3102', 'sport_id' => $sport->id, 'status' => 'Active']);
        MedicalRecord::create($this->payload($fourth, ['sport_id' => $sport->id, 'examination_date' => '2026-09-29']));

        $this->actingAs($admin)
            ->get(route('admin.medical', ['certificate' => 'Valid']))
            ->assertOk()
            ->assertSee('Sep 26, 2026')
            ->assertDontSee('Sep 27, 2026')
            ->assertDontSee('Sep 28, 2026')
            ->assertDontSee('Sep 29, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['certificate' => 'Expiring Soon']))
            ->assertOk()
            ->assertSee('Sep 27, 2026')
            ->assertDontSee('Sep 26, 2026')
            ->assertDontSee('Sep 28, 2026')
            ->assertDontSee('Sep 29, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['certificate' => 'Expired']))
            ->assertOk()
            ->assertSee('Sep 28, 2026')
            ->assertDontSee('Sep 26, 2026')
            ->assertDontSee('Sep 27, 2026')
            ->assertDontSee('Sep 29, 2026');

        $this->actingAs($admin)
            ->get(route('admin.medical', ['certificate' => 'Missing']))
            ->assertOk()
            ->assertSee('Sep 29, 2026')
            ->assertDontSee('Sep 26, 2026')
            ->assertDontSee('Sep 27, 2026')
            ->assertDontSee('Sep 28, 2026');
    }

    public function test_the_expiring_upcoming_and_overdue_panels_are_rendered(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id, 'next_checkup_date' => today()->addDays(5)->format('Y-m-d')]));

        $other = User::factory()->create(['role' => 'Student', 'name' => 'Luis Tan', 'sport_id' => $sport->id, 'status' => 'Active']);
        MedicalRecord::create($this->payload($other, ['sport_id' => $sport->id, 'medical_status' => 'Pending', 'next_checkup_date' => today()->addDays(45)->format('Y-m-d')]));

        $third = User::factory()->create(['role' => 'Student', 'name' => 'Nina Cruz', 'sport_id' => $sport->id, 'status' => 'Active']);
        MedicalRecord::create($this->payload($third, ['sport_id' => $sport->id, 'medical_status' => 'Not Cleared', 'next_checkup_date' => today()->subDays(3)->format('Y-m-d')]));

        $this->actingAs($admin)
            ->get(route('admin.medical'))
            ->assertOk()
            ->assertSee('Expiring Soon')
            ->assertSee('Upcoming Checkups')
            ->assertSee('Overdue Checkups')
            ->assertSee('Luis Tan')
            ->assertSee('Nina Cruz');
    }

    public function test_the_module_shows_an_empty_state_when_there_are_no_records(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)
            ->get(route('admin.medical'))
            ->assertOk()
            ->assertSee('No medical records found.')
            ->assertSee('No upcoming checkups.')
            ->assertSee('No medical certificates are expiring soon.')
            ->assertSee('No overdue checkups.')
            ->assertSee('No injuries or medical incidents recorded.');
    }

    public function test_an_administrator_can_record_an_injury_incident(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)
            ->post(route('admin.medical.incidents.store'), [
                'athlete_id' => $athlete->id,
                'sport_id' => $sport->id,
                'incident_date' => '2026-08-20',
                'activity' => 'Training drill',
                'injury_type' => 'Ankle sprain',
                'body_part' => 'Right ankle',
                'severity' => 'Moderate',
                'description' => 'Rolled the ankle during a footwork drill.',
                'treatment' => 'RICE and rest.',
                'rest_period_days' => 7,
                'return_to_play_date' => '2026-08-27',
                'medical_clearance' => 'Cleared',
            ])
            ->assertRedirect(route('admin.medical'));

        $this->assertDatabaseHas('medical_incidents', [
            'athlete_id' => $athlete->id,
            'injury_type' => 'Ankle sprain',
            'severity' => 'Moderate',
            'medical_clearance' => 'Cleared',
        ]);
    }

    public function test_an_incident_requires_an_athlete_student_account_and_an_assigned_sport(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport']);

        $base = [
            'incident_date' => '2026-08-20',
            'injury_type' => 'Ankle sprain',
            'severity' => 'Moderate',
            'description' => 'Rolled the ankle.',
            'medical_clearance' => 'Pending',
        ];

        $this->actingAs($admin)
            ->post(route('admin.medical.incidents.store'), ['athlete_id' => $admin->id] + $base)
            ->assertSessionHasErrors('athlete_id');

        $this->actingAs($admin)
            ->post(route('admin.medical.incidents.store'), ['athlete_id' => $athlete->id, 'sport_id' => $volleyball->id] + $base)
            ->assertSessionHasErrors('sport_id');

        $this->assertDatabaseCount('medical_incidents', 0);
    }

    public function test_an_administrator_can_update_and_delete_an_incident(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $incident = MedicalIncident::create([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'incident_date' => '2026-08-20',
            'injury_type' => 'Ankle sprain',
            'severity' => 'Moderate',
            'description' => 'Rolled the ankle.',
            'medical_clearance' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.medical.incidents.update', $incident), [
                'athlete_id' => $athlete->id,
                'sport_id' => $sport->id,
                'incident_date' => '2026-08-20',
                'injury_type' => 'Ankle ligament tear',
                'severity' => 'Severe',
                'description' => 'Confirmed strain after x-ray.',
                'medical_clearance' => 'Not Cleared',
            ])
            ->assertRedirect(route('admin.medical'));

        $this->assertDatabaseHas('medical_incidents', [
            'id' => $incident->id,
            'injury_type' => 'Ankle ligament tear',
            'severity' => 'Severe',
            'medical_clearance' => 'Not Cleared',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.medical.incidents.destroy', $incident))
            ->assertRedirect(route('admin.medical'));

        $this->assertDatabaseMissing('medical_incidents', ['id' => $incident->id]);
    }

    public function test_athletes_under_recovery_are_counted_in_the_summary(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        MedicalRecord::create($this->payload($athlete, ['sport_id' => $sport->id]));

        MedicalIncident::create([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'incident_date' => '2026-09-01',
            'injury_type' => 'Knee strain',
            'severity' => 'Moderate',
            'description' => 'Knee strain during sprint.',
            'medical_clearance' => 'Not Cleared',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.medical'))
            ->assertOk()
            ->assertSee('Under Recovery')
            ->assertSee('Knee strain');

        $this->assertSame(1, MedicalIncident::query()->underRecovery()->count());
    }
}
