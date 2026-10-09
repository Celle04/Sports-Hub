<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
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
        $event = Event::create([
            'title' => 'Regional Volleyball Tournament 2026',
            'sport_id' => $sport->id,
            'venue' => 'SNNHS Gymnasium',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addDays(3),
            'status' => 'Completed',
        ]);
        $achievement = Achievement::create([
            'athlete_id' => $athlete->id,
            'sport_id' => $sport->id,
            'event_id' => $event->id,
            'title' => 'Best Setter Award',
            'achievement_type' => 'Gold Medal',
            'competition' => 'Regional Volleyball Tournament 2026',
            'place' => '1st Place',
            'date_achieved' => '2026-09-15',
        ]);

        return [$admin, $sport, $athlete, $achievement];
    }

    public function test_the_administrator_can_open_the_certificate_generator(): void
    {
        [$admin, , , $achievement] = $this->context();

        $this->actingAs($admin)
            ->get(route('admin.achievements.certificate.generate'))
            ->assertOk()
            ->assertSee('Generate Achievement Certificate')
            ->assertSee($achievement->title);
    }

    public function test_selecting_an_achievement_shows_its_certificate_summary(): void
    {
        [$admin, , , $achievement] = $this->context();

        $this->actingAs($admin)
            ->get(route('admin.achievements.certificate.generate', ['achievement' => $achievement->id]))
            ->assertOk()
            ->assertSee('Best Setter Award')
            ->assertSee('Maria Santos')
            ->assertSee(route('admin.achievements.certificate.pdf', $achievement))
            ->assertSee(route('admin.achievements.certificate.print', $achievement));
    }

    public function test_the_printable_certificate_contains_the_real_achievement_data(): void
    {
        [$admin, $sport, $athlete, $achievement] = $this->context();

        $response = $this->actingAs($admin)
            ->get(route('admin.achievements.certificate.print', $achievement))
            ->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('SURIGAO DEL NORTE NATIONAL HIGH SCHOOL', $html);
        $this->assertStringContainsString('CERTIFICATE OF ACHIEVEMENT', $html);
        $this->assertStringContainsString($athlete->name, $html);
        $this->assertStringContainsString($athlete->student_id, $html);
        $this->assertStringContainsString($sport->name, $html);
        $this->assertStringContainsString('Best Setter Award', $html);
        $this->assertStringContainsString('Regional Volleyball Tournament 2026', $html);
        $this->assertStringContainsString('1st Place', $html);
        $this->assertStringContainsString('Gold Medal', $html);
        $this->assertStringContainsString('Sep 15, 2026', $html);
    }

    public function test_the_certificate_pdf_downloads_with_a_sanitized_filename(): void
    {
        [$admin, , , $achievement] = $this->context();

        $response = $this->actingAs($admin)
            ->get(route('admin.achievements.certificate.pdf', $achievement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $disposition = $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Certificate_of_Achievement_maria-santos_'.$achievement->id.'.pdf', $disposition);
    }

    public function test_the_generator_and_certificate_routes_are_admin_only(): void
    {
        [$admin, , $athlete, $achievement] = $this->context();

        $this->actingAs($athlete)->get(route('admin.achievements.certificate.generate'))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.achievements.certificate.print', $achievement))->assertForbidden();
        $this->actingAs($athlete)->get(route('admin.achievements.certificate.pdf', $achievement))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.achievements.certificate.generate'))->assertOk();
    }

    public function test_a_guest_is_redirected_away_from_the_certificate_routes(): void
    {
        [$admin] = $this->context();
        $achievement = Achievement::first();

        $this->get(route('admin.achievements.certificate.generate'))->assertRedirect(route('login'));
        $this->get(route('admin.achievements.certificate.print', $achievement))->assertRedirect(route('login'));
        $this->get(route('admin.achievements.certificate.pdf', $achievement))->assertRedirect(route('login'));
    }
}