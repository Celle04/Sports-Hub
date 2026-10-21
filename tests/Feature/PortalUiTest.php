<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_exposes_sidebar_and_theme_controls(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="portal-sidebar"', false);
        $response->assertSee('data-sidebar-toggle', false);
        $response->assertSee('portal-sidebar-collapse', false);
        $response->assertSee('portal-sidebar-open', false);
        $response->assertSee('id="theme-toggle"', false);
        $response->assertSee('aria-label="Collapse sidebar"', false);
        $response->assertSee('aria-label="Open navigation"', false);
        $response->assertSee('aria-label="Dark Mode"', false);
        $response->assertSee('sports_hub_theme', false);
        $response->assertSee('sports_hub_sidebar', false);
        $response->assertSee('js/portal-ui.js', false);
        $response->assertSee('icon-panel-left', false);
        $response->assertSee('icon-sun', false);
        $response->assertSee('icon-moon', false);
    }

    public function test_sidebar_links_expose_collapsed_state_tooltips(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertSee('data-tooltip="Dashboard"', false);
        $response->assertSee('data-tooltip="Reports"', false);
    }

    public function test_sidebar_groups_the_navigation_links(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('portal-nav-group-label', false);
        $response->assertSee('Overview');
        $response->assertSee('Programs');
        $response->assertSee('Operations');
        $response->assertSee('Records');
        $response->assertSee('Insights');
        $response->assertSee('Certificate Requests');
    }

    public function test_topbar_shows_the_page_context_and_administrator_identity(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'name' => 'Rina Admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('portal-breadcrumb', false);
        $response->assertSee('Admin Portal');
        $response->assertSee('Rina Admin');
        $response->assertSee('id="portal-notification-trigger"', false);
    }

    public function test_notification_panel_is_wired_to_its_trigger(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="portal-notification-trigger"', false);
        $response->assertSee('id="portal-notification-menu"', false);
        $response->assertSee('aria-labelledby="portal-notification-trigger"', false);
        $response->assertSee('aria-controls="portal-notification-menu"', false);
    }

    public function test_student_portal_uses_the_same_shared_controls(): void
    {
        $student = User::factory()->create(['role' => 'Student']);

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('data-sidebar-toggle', false);
        $response->assertSee('id="theme-toggle"', false);
        $response->assertSee('sports_hub_theme', false);
    }

    public function test_auth_layout_restores_theme_before_paint(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('sports_hub_theme', false);
        $response->assertSee('auth-card', false);
    }
}
