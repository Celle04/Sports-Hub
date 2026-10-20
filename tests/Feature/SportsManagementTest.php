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
            'gender' => 'Girls',
            'description' => 'Competitive swimming events',
        ])->assertRedirect(route('sports.index'));

        $sport = Sport::firstOrFail();
        $this->assertDatabaseHas('sports', ['name' => 'Swimming', 'gender' => 'Girls']);

        $this->put(route('sports.update', $sport), [
            'name' => 'Swimming and Diving',
            'classification' => 'Individual',
            'gender' => 'Boys',
            'description' => 'Updated swimming events',
        ])->assertRedirect(route('sports.index'));
        $this->assertDatabaseHas('sports', ['name' => 'Swimming and Diving', 'gender' => 'Boys']);

        $this->delete(route('sports.destroy', $sport->fresh()))->assertRedirect(route('sports.index'));
        $this->assertDatabaseMissing('sports', ['id' => $sport->id]);
    }

    public function test_gender_defaults_to_boys_when_omitted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $this->post(route('sports.store'), [
            'name' => 'Archery',
            'classification' => 'Individual',
            'description' => 'Target shooting sport',
        ])->assertRedirect(route('sports.index'));

        $this->assertDatabaseHas('sports', ['name' => 'Archery', 'gender' => 'Boys']);
    }

    public function test_gender_must_be_one_of_the_supported_options(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Administrator']));

        $this->from(route('sports.create'))
            ->post(route('sports.store'), [
                'name' => 'Fencing',
                'classification' => 'Combat Sport',
                'gender' => 'Mixed',
                'description' => 'Sword fighting sport',
            ])
            ->assertRedirect(route('sports.create'))
            ->assertSessionHasErrors('gender');

        $this->assertDatabaseMissing('sports', ['name' => 'Fencing']);
    }

    public function test_create_and_edit_forms_offer_the_gender_options(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)
            ->get(route('sports.create'))
            ->assertOk()
            ->assertSee('Boys')
            ->assertSee('Girls');

        $sport = Sport::create([
            'name' => 'Volleyball',
            'classification' => 'Team Sport',
            'gender' => 'Girls',
            'description' => 'Net sport',
        ]);

        $this->actingAs($admin)
            ->get(route('sports.edit', $sport))
            ->assertOk()
            ->assertSee('Boys')
            ->assertSee('Girls');
    }

    public function test_sports_can_be_filtered_by_gender(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'gender' => 'Boys', 'description' => 'Court sport']);
        Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'gender' => 'Girls', 'description' => 'Net sport']);

        $this->actingAs($admin)
            ->get(route('sports.index', ['gender' => 'Girls']))
            ->assertOk()
            ->assertSee('Volleyball')
            ->assertDontSee('Basketball');
    }
}