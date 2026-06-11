<?php

namespace Tests\Feature;

use App\Models\ConversationModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagerieConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_private_conversation(): void
    {
        $user = User::factory()->create();
        $destinataire = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/messagerie/conversations', [
            'destinataire_id' => $destinataire->id,
            'type' => 'private',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'private')
            ->assertJsonCount(2, 'data.users');

        $this->assertDatabaseHas('conversations', [
            'type' => 'private',
            'user_1_id' => min($user->id, $destinataire->id),
            'user_2_id' => max($user->id, $destinataire->id),
        ]);
    }

    public function test_private_conversation_creation_reuses_existing_conversation(): void
    {
        $user = User::factory()->create();
        $destinataire = User::factory()->create();
        $conversation = ConversationModel::create([
            'type' => 'private',
            'user_1_id' => $destinataire->id,
            'user_2_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/messagerie/conversations', [
            'destinataire_id' => $destinataire->id,
            'type' => 'private',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $conversation->id);

        $this->assertSame(1, ConversationModel::count());
    }
}
