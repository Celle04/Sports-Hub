<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Sport::create(['name' => 'Basketball', 'classification' => 'Team Sport', 'description' => 'Court game']);
    }

    public function test_valid_application_is_saved(): void
    {
        $response = $this->post(route('application.store'), [
            'name' => 'Juan Dela Cruz',
            'grade' => 'Grade 11',
            'email' => 'juan@example.com',
            'sport' => 'Basketball',
        ]);

        $response->assertRedirect(route('application.create'));
        $this->assertDatabaseHas('applications', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'sport' => 'Basketball',
            'status' => 'Pending',
        ]);
    }

    public function test_invalid_application_is_not_saved(): void
    {
        $response = $this->from(route('application.create'))->post(route('application.store'), [
            'name' => 'Juan Dela Cruz',
            'grade' => 'Grade 11',
            'email' => 'not-an-email',
            'sport' => 'Football',
        ]);

        $response->assertRedirect(route('application.create'));
        $response->assertSessionHasErrors(['email', 'sport']);
        $this->assertSame(0, Application::count());
    }
}