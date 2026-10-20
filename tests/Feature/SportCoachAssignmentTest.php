<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportCoachAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function coach(string $name, array $attributes = []): Coach
    {
        return Coach::create(array_merge([
            'name' => $name,
            'email' => str($name)->slug().'@example.com',
            'specialty' => 'Basketball',
            'coach_type' => 'Head Coach',
            'status' => 'Active',
        ], $attributes));
    }

    private function sportPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Basketball',
            'classification' => 'Team Sport',
            'gender' => 'Boys',
            'description' => 'Court sport',
        ], $overrides);
    }

    public function test_administrator_can_assign_a_coach_while_creating_a_sport(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $coach = $this->coach('Coach Roberto Martinez');

        $this->actingAs($admin)
            ->post(route('sports.store'), $this->sportPayload(['coach_id' => $coach->id]))
            ->assertRedirect(route('sports.index'));

        $sport = Sport::where('name', 'Basketball')->firstOrFail();

        $this->assertSame($sport->id, $coach->fresh()->sport_id);
        $this->assertTrue($sport->coaches->contains($coach));
    }

    public function test_administrator_can_change_the_assigned_coach_of_a_sport(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $firstCoach = $this->coach('Coach Roberto Martinez', ['sport_id' => $sport->id]);
        $secondCoach = $this->coach('Coach Linda Santos');

        $this->actingAs($admin)
            ->put(route('sports.update', $sport), $this->sportPayload(['coach_id' => $secondCoach->id]))
            ->assertRedirect(route('sports.index'));

        $this->assertSame($sport->id, $secondCoach->fresh()->sport_id);
        $this->assertNull($firstCoach->fresh()->sport_id, 'The replaced coach should be unassigned, never deleted.');
        $this->assertDatabaseHas('coaches', ['id' => $firstCoach->id, 'name' => 'Coach Roberto Martinez']);
    }

    public function test_the_assigned_coach_persists_and_is_shown_on_the_sport_pages(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $coach = $this->coach('Coach Roberto Martinez');

        $this->actingAs($admin)->put(route('sports.update', $sport), $this->sportPayload(['coach_id' => $coach->id]));

        $this->actingAs($admin)->get(route('sports.edit', $sport))
            ->assertOk()
            ->assertSee('Coach Assignment')
            ->assertSee('Coach Roberto Martinez');

        $this->actingAs($admin)->get(route('sports.show', $sport))
            ->assertOk()
            ->assertSee('Coach Roberto Martinez');

        $this->actingAs($admin)->get(route('sports.index'))
            ->assertOk()
            ->assertSee('Coach Roberto Martinez');
    }

    public function test_a_sport_can_be_saved_without_a_coach(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)
            ->post(route('sports.store'), $this->sportPayload(['coach_id' => '']))
            ->assertRedirect(route('sports.index'));

        $sport = Sport::where('name', 'Basketball')->firstOrFail();

        $this->assertCount(0, $sport->coaches);
        $this->assertNull($sport->assignedCoach());
    }

    public function test_selecting_a_coach_already_assigned_elsewhere_moves_them(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $volleyball = Sport::create(['name' => 'Volleyball', 'classification' => 'Team Sport', 'description' => 'Net sport']);
        $coach = $this->coach('Coach Roberto Martinez', ['sport_id' => $volleyball->id]);

        $this->actingAs($admin)
            ->put(route('sports.update', $basketball), $this->sportPayload(['coach_id' => $coach->id]))
            ->assertRedirect(route('sports.index'))
            ->assertSessionHas('success');

        $this->assertSame($basketball->id, $coach->fresh()->sport_id);
        $this->assertCount(0, $volleyball->fresh()->coaches);
    }

    public function test_an_invalid_coach_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)
            ->from(route('sports.create'))
            ->post(route('sports.store'), $this->sportPayload(['coach_id' => 9999]))
            ->assertRedirect(route('sports.create'))
            ->assertSessionHasErrors('coach_id');

        $this->assertDatabaseMissing('sports', ['name' => 'Basketball']);
    }

    public function test_students_cannot_assign_coaches(): void
    {
        $student = User::factory()->create(['role' => 'Student']);
        $coach = $this->coach('Coach Roberto Martinez');

        $this->actingAs($student)
            ->post(route('sports.store'), $this->sportPayload(['coach_id' => $coach->id]))
            ->assertForbidden();

        $this->assertNull($coach->fresh()->sport_id);
        $this->assertDatabaseMissing('sports', ['name' => 'Basketball']);
    }

    public function test_the_coaches_module_still_creates_coaches(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $this->coach('Coach Roberto Martinez');

        $this->actingAs($admin)->get(route('coaches.index'))
            ->assertOk()
            ->assertSee('Coach Roberto Martinez')
            ->assertSee('Add Coach');

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('coaches.create'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('coaches.store'));

        $this->actingAs($admin)->post(route('coaches.store'), [
            'name' => 'Coach Linda Santos',
            'email' => 'linda@example.com',
            'phone' => '09171234567',
            'specialty' => 'Basketball',
            'sport_id' => $sport->id,
            'coach_type' => 'Head Coach',
            'status' => 'Active',
        ])->assertRedirect(route('coaches.index'));

        $this->assertDatabaseHas('coaches', ['name' => 'Coach Linda Santos', 'sport_id' => $sport->id]);
    }

    public function test_the_coaches_module_explains_how_coaches_are_managed_when_empty(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->get(route('coaches.index'))
            ->assertOk()
            ->assertSee('No coaches are on record yet.')
            ->assertSee('Add a coach to get started')
            ->assertSee('Add Coach');
    }

    public function test_editing_a_coach_keeps_its_sport_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $coach = $this->coach('Coach Roberto Martinez', ['sport_id' => $sport->id]);

        $this->actingAs($admin)->get(route('coaches.edit', $coach))
            ->assertOk()
            ->assertSee('Assigned Sport')
            ->assertSee('Basketball');

        $this->actingAs($admin)->put(route('coaches.update', $coach), [
            'name' => 'Coach Roberto Martinez Jr.',
            'email' => $coach->email,
            'phone' => $coach->phone,
            'specialty' => $coach->specialty,
            'sport_id' => $sport->id,
            'coach_type' => 'Head Coach',
            'status' => 'Active',
        ])->assertRedirect(route('coaches.show', $coach));

        $this->assertSame($sport->id, $coach->fresh()->sport_id);
        $this->assertSame('Coach Roberto Martinez Jr.', $coach->fresh()->name);
    }

    public function test_the_coaches_module_keeps_search_filtering_and_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $this->coach('Coach Roberto Martinez', ['sport_id' => $sport->id]);
        $this->coach('Coach Linda Santos');

        $this->actingAs($admin)->get(route('coaches.index', ['search' => 'Roberto']))
            ->assertOk()
            ->assertSee('Coach Roberto Martinez')
            ->assertDontSee('Coach Linda Santos');

        $this->actingAs($admin)->get(route('coaches.index', ['sport_id' => $sport->id]))
            ->assertOk()
            ->assertSee('Coach Roberto Martinez')
            ->assertDontSee('Coach Linda Santos');
    }

    public function test_existing_coach_records_and_assignments_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport']);
        $coach = $this->coach('Coach Roberto Martinez', ['sport_id' => $sport->id]);

        $this->actingAs($admin)->get(route('coaches.index'))->assertOk()->assertSee('Coach Roberto Martinez');

        $this->assertDatabaseHas('coaches', ['id' => $coach->id, 'sport_id' => $sport->id, 'status' => 'Active']);
        $this->assertSame(1, Coach::count());
    }
}
