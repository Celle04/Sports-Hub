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
        $response->assertSee('id="sidebar-toggle"', false);
        $response->assertSee('id="theme-toggle"', false);
        $response->assertSee('aria-label="Toggle sidebar"', false);
        $response->assertSee('aria-label="Toggle dark mode"', false);
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

    public function test_student_portal_uses_the_same_shared_controls(): void
    {
        $student = User::factory()->create(['role' => 'Student']);

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('id="sidebar-toggle"', false);
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
