<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_guest_is_redirected_from_sports(): void
    {
        $this->get(route('sports.index'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_manage_sports(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Student']));

        $this->get(route('sports.index'))->assertForbidden();
        $this->post(route('sports.store'), [
            'name' => 'Swimming',
            'classification' => 'Individual',
            'description' => 'Competitive swimming events',
        ])->assertForbidden();
    }

    public function test_authenticated_user_can_perform_sports_crud(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $this->post(route('sports.store'), [
            'name' => 'Swimming',
            'classification' => 'Individual',
            'description' => 'Competitive swimming events',
        ])->assertRedirect(route('sports.index'));

        $sport = Sport::firstOrFail();
        $this->assertDatabaseHas('sports', ['name' => 'Swimming']);

        $this->put(route('sports.update', $sport), [
            'name' => 'Swimming and Diving',
            'classification' => 'Individual',
            'description' => 'Updated swimming events',
        ])->assertRedirect(route('sports.index'));
        $this->assertDatabaseHas('sports', ['name' => 'Swimming and Diving']);

        $this->delete(route('sports.destroy', $sport->fresh()))->assertRedirect(route('sports.index'));
        $this->assertDatabaseMissing('sports', ['id' => $sport->id]);
    }
}