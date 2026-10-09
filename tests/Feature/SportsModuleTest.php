<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_administrator_can_search_and_view_sport_details(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id, 'student_id' => '2026-1001']);
        Coach::create(['name' => 'Coach Santos', 'email' => 'coach@example.com', 'specialty' => 'Basketball', 'sport_id' => $sport->id]);
        Event::create(['title' => 'Basketball Practice', 'sport_id' => $sport->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'venue' => 'Main Court', 'status' => 'Scheduled']);
        Application::create(['name' => 'Basketball Applicant', 'student_id' => '2026-1002', 'grade' => 'Grade 10', 'gender' => 'Female', 'email' => 'applicant@example.com', 'sport' => 'Basketball', 'sport_id' => $sport->id, 'status' => 'Pending']);

        $this->actingAs($admin)->get(route('sports.index', ['search' => 'Basketball', 'status' => 'Active']))
            ->assertOk()
            ->assertSee('Basketball');
        $this->actingAs($admin)->get(route('sports.show', $sport))
            ->assertOk()
            ->assertSee('Basketball Practice')
            ->assertSee('Coach Santos')
            ->assertSee('Basketball Applicant');
    }

    public function test_referenced_sport_is_deactivated_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $sport = Sport::create(['name' => 'Chess', 'classification' => 'Mind Sport', 'description' => 'Strategy sport', 'status' => 'Active']);
        User::factory()->create(['role' => 'Student', 'sport_id' => $sport->id]);

        $this->actingAs($admin)->delete(route('sports.destroy', $sport));

        $this->assertDatabaseHas('sports', ['id' => $sport->id, 'status' => 'Inactive']);
    }

    public function test_student_roster_returns_only_active_members_of_the_selected_sport_and_supports_filters(): void
    {
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $badminton = Sport::create(['name' => 'Badminton', 'classification' => 'Racket Sport', 'description' => 'Racket sport', 'status' => 'Active']);

        $approvedStudent = User::factory()->create([
            'name' => 'Mika Santos',
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $basketball->id,
            'student_id' => 'B-1001',
        ]);
        $activeStudent = User::factory()->create([
            'name' => 'Lia Reyes',
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $basketball->id,
            'student_id' => 'B-1002',
        ]);
        $pendingStudent = User::factory()->create([
            'name' => 'Pending Student',
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $basketball->id,
        ]);
        $rejectedStudent = User::factory()->create([
            'name' => 'Rejected Student',
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $basketball->id,
        ]);
        $badmintonStudent = User::factory()->create([
            'name' => 'Badminton Student',
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $badminton->id,
        ]);

        Application::create([
            'name' => $approvedStudent->name,
            'student_id' => $approvedStudent->student_id,
            'grade' => 'Grade 10',
            'email' => $approvedStudent->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'status' => 'Approved',
            'athlete_id' => $approvedStudent->id,
        ]);
        Application::create([
            'name' => $pendingStudent->name,
            'grade' => 'Grade 9',
            'email' => $pendingStudent->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'status' => 'Pending',
            'athlete_id' => $pendingStudent->id,
        ]);
        Application::create([
            'name' => $rejectedStudent->name,
            'grade' => 'Grade 11',
            'email' => $rejectedStudent->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'status' => 'Rejected',
            'athlete_id' => $rejectedStudent->id,
        ]);

        $student = User::factory()->create(['role' => 'Student']);

        $this->actingAs($student)
            ->getJson(route('student.sports.members', $basketball))
            ->assertOk()
            ->assertJsonPath('program.name', 'Basketball')
            ->assertJsonPath('total_members', 2)
            ->assertJsonPath('members.total', 2)
            ->assertJsonPath('members.data.0.profile_url', route('student.sports.member-profile', [$basketball, $activeStudent]))
            ->assertJsonFragment(['name' => 'Mika Santos', 'enrollment_status' => 'Approved'])
            ->assertJsonFragment(['name' => 'Lia Reyes', 'enrollment_status' => 'Active'])
            ->assertJsonMissing(['name' => 'Pending Student'])
            ->assertJsonMissing(['name' => 'Rejected Student'])
            ->assertJsonMissing(['name' => 'Badminton Student']);

        $this->getJson(route('student.sports.members', [$basketball, 'grade' => 'Grade 10']))
            ->assertOk()
            ->assertJsonPath('members.total', 1)
            ->assertJsonPath('members.data.0.name', 'Mika Santos');

        $this->getJson(route('student.sports.members', [$basketball, 'status' => 'Active']))
            ->assertOk()
            ->assertJsonPath('members.total', 1)
            ->assertJsonPath('members.data.0.name', 'Lia Reyes');

        $this->getJson(route('student.sports.members', [$badminton]))
            ->assertOk()
            ->assertJsonPath('total_members', 1)
            ->assertJsonPath('members.data.0.name', 'Badminton Student');

        $this->getJson(route('student.sports.member-profile', [$basketball, $pendingStudent]))
            ->assertNotFound();

        $this->getJson(route('student.sports.member-profile', [$basketball, $rejectedStudent]))
            ->assertNotFound();
    }

    public function test_student_can_view_only_safe_profile_fields_for_a_member_of_the_selected_sport(): void
    {
        $basketball = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $badminton = Sport::create(['name' => 'Badminton', 'classification' => 'Racket Sport', 'description' => 'Racket sport', 'status' => 'Active']);
        Coach::create(['name' => 'Coach Santos', 'email' => 'coach@example.com', 'specialty' => 'Basketball', 'sport_id' => $basketball->id]);
        $athlete = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $basketball->id,
            'student_id' => '2026-001',
            'phone' => '555-0100',
        ]);
        $otherAthlete = User::factory()->create([
            'role' => 'Student',
            'status' => 'Active',
            'sport_id' => $badminton->id,
        ]);
        Application::create([
            'name' => $athlete->name,
            'student_id' => $athlete->student_id,
            'grade' => 'Grade 10',
            'email' => $athlete->email,
            'sport' => $basketball->name,
            'sport_id' => $basketball->id,
            'status' => 'Approved',
            'reviewed_at' => now()->subMonth(),
            'athlete_id' => $athlete->id,
        ]);

        $student = User::factory()->create(['role' => 'Student']);

        $this->actingAs($student)
            ->getJson(route('student.sports.member-profile', [$basketball, $athlete]))
            ->assertOk()
            ->assertJsonStructure(['profile' => ['name', 'student_id', 'grade', 'photo_url', 'status', 'sport', 'coaches', 'date_joined']])
            ->assertJsonPath('profile.name', 'Juan Dela Cruz')
            ->assertJsonPath('profile.student_id', '2026-001')
            ->assertJsonPath('profile.grade', 'Grade 10')
            ->assertJsonPath('profile.status', 'Active')
            ->assertJsonPath('profile.sport', 'Basketball')
            ->assertJsonPath('profile.coaches.0', 'Coach Santos')
            ->assertJsonPath('profile.date_joined', now()->subMonth()->format('F j, Y'))
            ->assertJsonMissingPath('profile.email')
            ->assertJsonMissingPath('profile.phone')
            ->assertJsonMissingPath('profile.username')
            ->assertJsonMissingPath('profile.password')
            ->assertJsonMissingPath('profile.medical_records');

        $this->getJson(route('student.sports.member-profile', [$basketball, $otherAthlete]))
            ->assertNotFound();

        $this->getJson(route('student.sports.member-profile', [$badminton, $athlete]))
            ->assertNotFound();
    }

    public function test_only_authenticated_students_can_view_a_sports_roster(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);

        $this->getJson(route('student.sports.members', $sport))->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->getJson(route('student.sports.members', $sport))
            ->assertForbidden();
    }

    public function test_only_authenticated_students_can_view_a_member_profile(): void
    {
        $sport = Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court sport', 'status' => 'Active']);
        $athlete = User::factory()->create(['role' => 'Student', 'status' => 'Active', 'sport_id' => $sport->id]);

        $this->getJson(route('student.sports.member-profile', [$sport, $athlete]))->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->getJson(route('student.sports.member-profile', [$sport, $athlete]))
            ->assertForbidden();
    }
}
