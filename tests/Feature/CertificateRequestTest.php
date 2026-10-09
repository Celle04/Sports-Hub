<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\CertificateRequest;
use App\Models\Sport;
use App\Models\User;
use App\Notifications\CertificateRequestNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('private');
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Sport, 2: \App\Models\User, 3: \App\Models\Achievement}
     */
    private function context(): array
    {
        $sport = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport', 'status' => 'Active']);
        $admin = User::factory()->create(['role' => 'Administrator', 'name' => 'Admin User']);
        $athlete = User::factory()->create([
            'role' => 'Student',
            'name' => 'Maria Santos',
            'student_id' => 'S-1420',
            'sport_id' => $sport->id,
            'status' => 'Active',
        ]);
        $achievement = Achievement::create([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'title' => 'Best Setter Award',
            'achievement_type' => 'Gold Medal',
            'competition' => 'Regional Volleyball Tournament 2026',
            'place' => '1st Place',
            'date_achieved' => '2026-09-15',
        ]);

        return [$admin, $sport, $athlete, $achievement];
    }

    public function test_a_student_can_request_a_certificate_for_their_own_achievement(): void
    {
        [, , $athlete, $achievement] = $this->context();

        $response = $this->actingAs($athlete)
            ->post(route('student.achievements.certificate-request', $achievement))
            ->assertRedirect();

        $this->assertDatabaseHas('certificate_requests', [
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        $response->assertSessionHas('success');
    }

    public function test_a_student_cannot_request_a_certificate_for_someone_elses_achievement(): void
    {
        [, , $athlete, $achievement] = $this->context();
        $otherAthlete = User::factory()->create(['role' => 'Student', 'status' => 'Active']);

        $this->actingAs($otherAthlete)
            ->post(route('student.achievements.certificate-request', $achievement))
            ->assertNotFound();

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_a_student_cannot_request_a_certificate_when_one_is_already_attached(): void
    {
        [, , $athlete, $achievement] = $this->context();
        $path = 'achievement-certificates/existing.pdf';
        Storage::disk('private')->put($path, 'existing certificate');
        $achievement->update(['certificate_path' => $path]);

        $this->assertTrue($achievement->hasCertificate());

        $this->actingAs($athlete)
            ->post(route('student.achievements.certificate-request', $achievement))
            ->assertSessionHasErrors('certificate');

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_a_student_cannot_duplicate_a_pending_request(): void
    {
        [, , $athlete, $achievement] = $this->context();
        CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        $this->actingAs($athlete)
            ->post(route('student.achievements.certificate-request', $achievement))
            ->assertSessionHasErrors('certificate');

        $this->assertDatabaseCount('certificate_requests', 1);
    }

    public function test_a_student_can_request_again_after_a_rejection(): void
    {
        [, , $athlete, $achievement] = $this->context();
        CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_REJECTED,
            'remarks' => 'Missing proof of participation.',
        ]);

        $this->actingAs($athlete)
            ->post(route('student.achievements.certificate-request', $achievement))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('certificate_requests', [
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
            'remarks' => null,
        ]);
        $this->assertSame('Missing proof of participation.', CertificateRequest::where('achievement_id', $achievement->id)->first()->remarks);
        $this->assertDatabaseCount('certificate_requests', 2);
    }

    public function test_submitting_a_request_notifies_all_administrators(): void
    {
        Notification::fake();
        [, , $athlete, $achievement] = $this->context();
        $secondAdmin = User::factory()->create(['role' => 'Administrator', 'name' => 'Second Admin']);

        $this->actingAs($athlete)
            ->post(route('student.achievements.certificate-request', $achievement))
            ->assertRedirect();

        Notification::assertSentTo(
            User::where('role', 'Administrator')->get(),
            CertificateRequestNotification::class,
        );
    }

    public function test_the_admin_module_is_administrator_only(): void
    {
        [$admin, , $athlete, $achievement] = $this->context();
        $request = CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        $this->actingAs($athlete)->get(route('admin.certificate-requests'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.certificate-requests'))
            ->assertOk()
            ->assertSee('Certificate Requests')
            ->assertSee('Maria Santos')
            ->assertSee('Best Setter Award')
            ->assertSee('Approve');
    }

    public function test_an_administrator_can_approve_a_pending_request(): void
    {
        [$admin, , $athlete, $achievement] = $this->context();
        $request = CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.approve', $request))
            ->assertRedirect();

        $this->assertDatabaseHas('certificate_requests', [
            'id' => $request->id,
            'status' => CertificateRequest::STATUS_APPROVED,
            'handled_by' => $admin->id,
        ]);
    }

    public function test_an_administrator_can_issue_a_certificate_attaching_it_to_the_achievement(): void
    {
        [$admin, , $athlete, $achievement] = $this->context();
        $request = CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.issue', $request))
            ->assertRedirect();

        $request->refresh();
        $achievement->refresh();

        $this->assertSame(CertificateRequest::STATUS_ISSUED, $request->status);
        $this->assertNotNull($request->certificate_path);
        $this->assertSame($request->certificate_path, $achievement->certificate_path);
        $this->assertTrue($achievement->hasCertificate());

        $this->actingAs($athlete)
            ->get(route('student.achievements.certificate', $achievement))
            ->assertOk();

        $this->actingAs($athlete)
            ->get(route('student.achievements.show', $achievement))
            ->assertOk()
            ->assertSee('Certificate issued');
    }

    public function test_an_administrator_can_reject_a_request_with_remarks(): void
    {
        [$admin, , $athlete, $achievement] = $this->context();
        $request = CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.reject', $request), ['remarks' => 'No official record found.'])
            ->assertRedirect();

        $this->assertDatabaseHas('certificate_requests', [
            'id' => $request->id,
            'status' => CertificateRequest::STATUS_REJECTED,
            'handled_by' => $admin->id,
            'remarks' => 'No official record found.',
        ]);
    }

    public function test_the_student_achievement_page_shows_the_open_request_status(): void
    {
        [, , $athlete, $achievement] = $this->context();
        CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $athlete->id,
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        $this->actingAs($athlete)
            ->get(route('student.achievements'))
            ->assertOk()
            ->assertSee('Certificate requested');
    }
}