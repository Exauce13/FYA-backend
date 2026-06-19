<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_locked_for_one_hour_after_three_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'Secret12#',
            'statut' => 'clients',
            'telephone' => '97000000',
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'Wrong12#',
        ];

        $this->postJson('/api/login', $payload)
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'tentatives_restantes' => 2,
            ]);

        $this->postJson('/api/login', $payload)
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'tentatives_restantes' => 1,
            ]);

        $this->postJson('/api/login', $payload)
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'tentatives_restantes' => 0,
            ])
            ->assertJsonStructure(['retry_after']);

        $this->postJson('/api/login', $payload)
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'tentatives_restantes' => 0,
            ]);
    }
}
