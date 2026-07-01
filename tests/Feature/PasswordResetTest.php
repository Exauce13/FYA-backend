<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_response_and_stores_hashed_token(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://frontend.test']);

        $user = User::factory()->create([
            'email' => 'client@example.com',
            'statut' => 'clients',
            'telephone' => '97000000',
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Si cette adresse existe, un lien de reinitialisation a ete envoye.',
            ]);

        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->assertNotNull($row);

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($row, $user) {
            parse_str((string) parse_url($mail->resetUrl, PHP_URL_QUERY), $query);

            return $mail->hasTo($user->email)
                && $query['email'] === $user->email
                && Hash::check($query['token'], $row->token)
                && $query['token'] !== $row->token;
        });
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/forgot-password', [
            'email' => 'unknown@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Si cette adresse existe, un lien de reinitialisation a ete envoye.',
            ]);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_reset_password_updates_password_deletes_tokens_and_sends_notification(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://frontend.test']);

        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'OldPass1#',
            'statut' => 'clients',
            'telephone' => '97000000',
        ]);

        Sanctum::actingAs($user);
        $user->tokens()->create([
            'name' => 'auth_token',
            'token' => hash('sha256', 'plain-token'),
            'abilities' => ['*'],
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $resetMail = Mail::sent(ResetPasswordMail::class)->first();
        parse_str((string) parse_url($resetMail->resetUrl, PHP_URL_QUERY), $query);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $query['token'],
            'password' => 'NewPass1#',
            'password_confirmation' => 'NewPass1#',
            'confirm_password' => 'NewPass1#',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Votre mot de passe a ete reinitialise.',
            ]);

        $user->refresh();

        $this->assertTrue(Hash::check('NewPass1#', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail) => $mail->hasTo($user->email));
    }

    public function test_reset_password_rejects_expired_token(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'client@example.com',
            'statut' => 'clients',
            'telephone' => '97000000',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('expired-token'),
            'created_at' => now()->subMinutes(61),
        ]);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'expired-token',
            'password' => 'NewPass1#',
            'password_confirmation' => 'NewPass1#',
            'confirm_password' => 'NewPass1#',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);

        $this->assertFalse(Hash::check('NewPass1#', $user->refresh()->password));
        Mail::assertNothingSent();
    }
}
