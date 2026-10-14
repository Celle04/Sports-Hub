<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_reset_link_requests_are_rate_limited_per_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'someone@example.com'])
                ->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'someone@example.com'])
            ->assertStatus(429);
    }

    public function test_reset_submissions_are_rate_limited_per_minute(): void
    {
        $payload = [
            'email' => 'someone@example.com',
            'token' => 'forged-token',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.update'), $payload)
                ->assertRedirect();
        }

        $this->post(route('password.update'), $payload)
            ->assertStatus(429);
    }
}
