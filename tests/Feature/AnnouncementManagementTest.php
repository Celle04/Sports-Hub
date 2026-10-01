<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_view_announcements_dashboard_and_search(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $sport = Sport::create([
            'name' => 'Basketball',
            'classification' => 'Team Sport',
            'description' => 'Basketball program',
        ]);

        Announcement::create([
            'title' => 'Basketball Tryouts',
            'body' => 'Tryouts are on Friday at 3:00 PM.',
            'sport_id' => $sport->id,
            'published_at' => now()->toDateString(),
            'status' => 'Published',
        ]);

        $this->get(route('admin.announcements'))
            ->assertOk()
            ->assertSee('Announcement Management')
            ->assertSee('Total Announcements')
            ->assertSee('Published')
            ->assertSee('Search announcements...');

        $this->get(route('admin.announcements', ['search' => 'Tryouts']))
            ->assertOk()
            ->assertSee('Basketball Tryouts');
    }

    public function test_admin_can_create_announcement_with_valid_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));
        $sport = Sport::create([
            'name' => 'Volleyball',
            'classification' => 'Team Sport',
            'description' => 'Volleyball program',
        ]);

        $this->post(route('admin.announcements.store'), [
            'title' => 'Volleyball Training',
            'body' => 'Training begins next Tuesday.',
            'sport_id' => $sport->id,
            'published_at' => now()->toDateString(),
            'status' => 'Published',
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', ['title' => 'Volleyball Training']);
    }
}
