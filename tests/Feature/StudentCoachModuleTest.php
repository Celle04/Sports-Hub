<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCoachModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function sport(string $name): Sport
    {
        return Sport::create(['name' => $name, 'classification' => 'Team Sport', 'description' => $name.' program', 'status' => 'Active']);
    }

    private function coach(Sport $sport, string $name, array $attributes = []): Coach
    {
        return Coach::create(array_merge([
            'name' => $name,
            'email' => str($name)->slug().'@example.com',
            'specialty' => $sport->name,
            'coach_type' => 'Head Coach',
            'sport_id' => $sport->id,
            'status' => 'Active',
        ], $attributes));
    }

    public function test_coach_module_only_lists_coaches_of_the_students_sport(): void
    {
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');
        $this->coach($basketball, 'Juan Dela Cruz');
        $this->coach($volleyball, 'Maria Santos');
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id]);

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('Basketball')
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('Volleyball');
    }

    public function test_coach_module_shows_every_coach_assigned_to_the_students_sport(): void
    {
        $basketball = $this->sport('Basketball');
        $this->coach($basketball, 'Juan Dela Cruz');
        $this->coach($basketball, 'Pedro Lim', ['coach_type' => 'Assistant Coach']);
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id]);

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Pedro Lim')
            ->assertSee('Assistant Coach')
            ->assertSee('Assigned to Basketball');
    }

    public function test_student_joined_to_multiple_sports_sees_coaches_from_each_of_them(): void
    {
        $basketball = $this->sport('Basketball');
        $athletics = $this->sport('Athletics');
        $volleyball = $this->sport('Volleyball');
        $this->coach($basketball, 'Juan Dela Cruz');
        $this->coach($athletics, 'Rina Ocampo');
        $this->coach($volleyball, 'Maria Santos');
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id, 'student_id' => 'S-3001']);
        Application::create([
            'name' => $student->name,
            'student_id' => $student->student_id,
            'grade' => 'Grade 11',
            'email' => $student->email,
            'sport' => $athletics->name,
            'sport_id' => $athletics->id,
            'status' => 'Approved',
            'athlete_id' => $student->id,
        ]);

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('Basketball')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Athletics')
            ->assertSee('Rina Ocampo')
            ->assertDontSee('Maria Santos');
    }

    public function test_pending_application_does_not_grant_access_to_another_sports_coach(): void
    {
        $basketball = $this->sport('Basketball');
        $volleyball = $this->sport('Volleyball');
        $this->coach($basketball, 'Juan Dela Cruz');
        $this->coach($volleyball, 'Maria Santos');
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id]);
        Application::create([
            'name' => $student->name,
            'grade' => 'Grade 11',
            'email' => $student->email,
            'sport' => $volleyball->name,
            'sport_id' => $volleyball->id,
            'status' => 'Pending',
            'athlete_id' => $student->id,
        ]);

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Maria Santos');
    }

    public function test_sport_without_a_coach_shows_the_empty_state(): void
    {
        $basketball = $this->sport('Basketball');
        $this->coach($basketball, 'Juan Dela Cruz');
        $this->coach($basketball, 'Pedro Lim');
        $athletics = $this->sport('Athletics');
        $inactiveCoach = $this->coach($basketball, 'Retired Coach', ['status' => 'Inactive']);
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id]);
        Application::create([
            'name' => $student->name,
            'grade' => 'Grade 11',
            'email' => $student->email,
            'sport' => $athletics->name,
            'sport_id' => $athletics->id,
            'status' => 'Approved',
            'athlete_id' => $student->id,
        ]);

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Pedro Lim')
            ->assertDontSee($inactiveCoach->name)
            ->assertSee('Athletics')
            ->assertSee('No coach assigned for this sport yet.');
    }

    public function test_student_without_a_sport_is_told_to_get_assigned_one(): void
    {
        $basketball = $this->sport('Basketball');
        $this->coach($basketball, 'Juan Dela Cruz');
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => null]);

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('You are not assigned to any sport yet.')
            ->assertDontSee('Juan Dela Cruz');
    }

    public function test_admin_coach_assignment_is_reflected_for_the_assigned_students(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $basketball = $this->sport('Basketball');
        $student = User::factory()->create(['role' => 'Student', 'sport_id' => $basketball->id]);

        $this->actingAs($admin)->post(route('coaches.store'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'coach@example.com',
            'phone' => '09171234567',
            'specialty' => 'Basketball',
            'sport_id' => $basketball->id,
            'coach_type' => 'Head Coach',
            'status' => 'Active',
        ])->assertRedirect(route('coaches.index'));

        $this->actingAs($student)->get(route('student.coach'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('coach@example.com');

        $this->assertSame($basketball->id, $student->sportIds()[0]);
        $this->assertSame(['Juan Dela Cruz'], $student->coaches()->pluck('name')->all());
    }

    public function test_administrator_cannot_open_the_student_coach_module(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $this->actingAs($admin)->get(route('student.coach'))->assertForbidden();
    }
}
