<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\CertificateRequest;
use App\Models\Sport;
use App\Models\User;
use App\Services\AchievementCertificateService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The certificate issuance workflow.
 *
 * A certificate is always a generated PDF that is stored on the private disk
 * and linked to the request it belongs to. Nothing is uploaded, and the
 * student only receives the file once an administrator marked it ready for
 * release.
 */
class CertificateIssuanceTest extends TestCase
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
    private function context(): array
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $admin = User::factory()->create(['role' => 'Administrator', 'name' => 'Rina Admin']);
        $athlete = User::factory()->create(['role' => 'Student', 'name' => 'Juan Dela Cruz', 'student_id' => 'S-2048', 'sport_id' => $sport->id, 'status' => 'Active']);

        return [$admin, $sport, $athlete];
    }

    private function achievement(User $athlete, Sport $sport, array $overrides = []): Achievement
    {
        return Achievement::create(array_merge([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'title' => 'Gold Medal - 100 Meter Sprint',
            'achievement_type' => 'Gold Medal',
            'competition' => 'Caraga Regional Athletic Games 2026',
            'place' => '1st Place',
            'date_achieved' => '2026-09-15',
        ], $overrides));
    }

    private function request(Achievement $achievement, User $student, string $status = CertificateRequest::STATUS_PENDING): CertificateRequest
    {
        return CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }

    public function test_the_generated_certificate_is_a_real_pdf_on_the_private_disk(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);
        $request = $this->request($achievement, $athlete);

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.approve', $request))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.generate', $request))
            ->assertSessionHas('success');

        $request->refresh();

        $this->assertNotNull($request->certificate_path);
        $this->assertStringStartsWith('achievement-certificates/', $request->certificate_path);
        Storage::disk('private')->assertExists($request->certificate_path);

        // A real PDF document, not an HTML page with a .pdf name.
        $contents = Storage::disk('private')->get($request->certificate_path);
        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertGreaterThan(1000, strlen($contents));

        // The achievement carries the same file.
        $this->assertSame($request->certificate_path, $achievement->fresh()->certificate_path);
    }

    public function test_the_certificate_carries_the_school_details_the_recipient_and_a_reference(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);

        $html = $this->actingAs($admin)
            ->get(route('admin.achievements.certificate.print', $achievement))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('SURIGAO DEL NORTE NATIONAL HIGH SCHOOL', $html);
        $this->assertStringContainsString('CERTIFICATE OF ACHIEVEMENT', $html);
        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringContainsString('Basketball', $html);
        $this->assertStringContainsString('Gold Medal - 100 Meter Sprint', $html);
        $this->assertStringContainsString('Caraga Regional Athletic Games 2026', $html);
        $this->assertStringContainsString('1st Place', $html);
        $this->assertStringContainsString('Sep 15, 2026', $html);
        $this->assertStringContainsString('Rina Admin', $html);
        $this->assertStringContainsString('Sports Coordinator', $html);
        $this->assertStringContainsString('SNNHS-ACH-2026-'.str_pad((string) $achievement->id, 4, '0', STR_PAD_LEFT), $html);
    }

    public function test_a_team_achievement_is_recorded_and_issued_to_the_team(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)->post(route('admin.achievements.store'), [
            'is_team' => '1',
            'team_name' => 'Varsity Badminton Girls',
            'sport_id' => $sport->id,
            'title' => 'Team Championship',
            'achievement_type' => 'Champion',
            'competition' => 'Regional Athletic Games',
            'place' => '1st Place',
            'date_achieved' => '2026-09-20',
        ])->assertRedirect(route('admin.achievements'));

        $achievement = Achievement::firstOrFail();

        $this->assertTrue($achievement->isTeam());
        $this->assertNull($achievement->athlete_id);
        $this->assertSame('Varsity Badminton Girls', $achievement->recipientName());
        $this->assertSame('Team', $achievement->recipientLabel());

        // The certificate is issued to the team.
        $this->actingAs($admin)
            ->post(route('admin.achievements.certificate.issue', $achievement))
            ->assertSessionHas('success');

        $achievement->refresh();
        $this->assertNotNull($achievement->certificate_path);
        Storage::disk('private')->assertExists($achievement->certificate_path);
        $this->assertDatabaseHas('certificate_requests', [
            'achievement_id' => $achievement->id,
            'status' => CertificateRequest::STATUS_ISSUED,
        ]);
    }

    public function test_a_team_achievement_needs_a_team_name(): void
    {
        [$admin, $sport, $athlete] = $this->context();

        $this->actingAs($admin)->post(route('admin.achievements.store'), [
            'is_team' => '1',
            'sport_id' => $sport->id,
            'title' => 'Team Championship',
            'achievement_type' => 'Champion',
            'date_achieved' => '2026-09-20',
        ])->assertSessionHasErrors('team_name');

        $this->assertSame(0, Achievement::count());
    }

    public function test_the_request_walks_from_approved_to_generated_to_released(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);
        $request = $this->request($achievement, $athlete);

        // A pending request cannot be generated.
        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.generate', $request))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.approve', $request))
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame(CertificateRequest::STATUS_APPROVED, $request->status);
        $this->assertFalse($request->isGenerated());

        // Generate: the file exists, the request stays approved.
        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.generate', $request))
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertTrue($request->isGenerated());
        $this->assertSame(CertificateRequest::STATUS_APPROVED, $request->status);
        $this->assertNotNull($request->certificate_generated_at);
        $this->assertSame('SNNHS-ACH-2026-'.str_pad((string) $achievement->id, 4, '0', STR_PAD_LEFT), $request->certificate_reference);

        // Release: the request is issued and the athlete can download it.
        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.release', $request))
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame(CertificateRequest::STATUS_ISSUED, $request->status);
        $this->assertTrue($request->isReleased());

        $this->actingAs($athlete)
            ->get(route('student.achievements.certificate', $achievement))
            ->assertOk();
    }

    public function test_a_student_cannot_approve_generate_or_release_a_request(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);
        $request = $this->request($achievement, $athlete);

        $this->actingAs($athlete)->post(route('admin.certificate-requests.approve', $request))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.certificate-requests.generate', $request))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.certificate-requests.release', $request))->assertForbidden();
        $this->actingAs($athlete)->post(route('admin.certificate-requests.issue', $request))->assertForbidden();
    }

    public function test_a_request_that_does_not_belong_to_the_achievement_is_rejected(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $other = User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'status' => 'Active']);
        $achievement = $this->achievement($athlete, $sport);

        // The request points at another athlete's achievement.
        $request = CertificateRequest::create([
            'achievement_id' => $achievement->id,
            'student_id' => $other->id,
            'status' => CertificateRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.certificate-requests.generate', $request))
            ->assertStatus(422);

        $this->assertNull($request->fresh()->certificate_path);
    }

    public function test_regenerating_a_certificate_keeps_the_previous_file_in_the_history(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);
        $request = $this->request($achievement, $athlete, CertificateRequest::STATUS_APPROVED);

        $this->actingAs($admin)->post(route('admin.certificate-requests.generate', $request));
        $firstPath = $request->fresh()->certificate_path;

        $this->actingAs($admin)->post(route('admin.certificate-requests.generate', $request));
        $secondPath = $request->fresh()->certificate_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('private')->assertExists($secondPath);
        Storage::disk('private')->assertMissing($firstPath);

        // The superseded file is kept in the history folder.
        $history = Storage::disk('private')->allFiles('achievement-certificates/history');
        $this->assertCount(1, $history);
    }

    public function test_a_long_team_name_still_produces_a_certificate(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $longName = 'Surrigao del Norte National High School Varsity Badminton Girls A Team';
        $achievement = $this->achievement($athlete, $sport, ['athlete_id' => null, 'team_name' => $longName]);

        $this->actingAs($admin)
            ->post(route('admin.achievements.certificate.issue', $achievement))
            ->assertSessionHas('success');

        $contents = Storage::disk('private')->get($achievement->fresh()->certificate_path);
        $this->assertStringStartsWith('%PDF-', $contents);
    }

    public function test_the_certificate_fits_on_a_single_a4_landscape_page(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport, [
            'athlete_id' => null,
            'team_name' => 'Surrigao del Norte National High School Varsity Badminton Girls A Team',
            'title' => 'Overall Champions at the 38th Regional Athletic Games',
        ]);

        $dompdf = new Dompdf([
            'enable_font_subsetting' => true,
            'isRemoteEnabled' => false,
        ]);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('admin.achievements.certificate', app(AchievementCertificateService::class)->data($achievement, true))->render());
        $dompdf->render();

        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
    }

    public function test_the_certificate_requests_module_shows_the_status_and_the_generation_state(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);
        $request = $this->request($achievement, $athlete, CertificateRequest::STATUS_APPROVED);

        $this->actingAs($admin)->post(route('admin.certificate-requests.generate', $request));

        $this->actingAs($admin)->get(route('admin.certificate-requests'))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee('PDF generated')
            ->assertSee('Regenerate PDF')
            ->assertSee('Mark Ready for Release');
    }

    public function test_the_certificate_request_interface_has_no_upload_control(): void
    {
        [$admin, $sport, $athlete] = $this->context();
        $achievement = $this->achievement($athlete, $sport);
        $this->request($achievement, $athlete);

        $this->actingAs($admin)->get(route('admin.certificate-requests'))
            ->assertOk()
            ->assertDontSee('type="file"', false)
            ->assertDontSee('name="certificate"', false);
    }
}
