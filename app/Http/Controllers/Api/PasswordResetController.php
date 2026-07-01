<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private const FORGOT_PASSWORD_MAX_ATTEMPTS = 5;

    private const FORGOT_PASSWORD_LOCK_SECONDS = 3600;

    private const RESET_TOKEN_TTL_MINUTES = 60;

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'L adresse email est obligatoire.',
            'email.email' => 'Adresse email invalide.',
        ]);

        $email = Str::lower($validated['email']);
        $throttleKey = $this->forgotPasswordThrottleKey($request, $email);

        if (RateLimiter::tooManyAttempts($throttleKey, self::FORGOT_PASSWORD_MAX_ATTEMPTS)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de demandes. Veuillez reessayer plus tard.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        RateLimiter::hit($throttleKey, self::FORGOT_PASSWORD_LOCK_SECONDS);

        $user = User::where('email', $email)->first();

        if ($user) {
            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]);

            Mail::to($user->email)->send(new ResetPasswordMail(
                user: $user,
                resetUrl: $this->resetUrl($plainToken, $email),
                expiresInMinutes: self::RESET_TOKEN_TTL_MINUTES,
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Si cette adresse existe, un lien de reinitialisation a ete envoye.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_\-#])[A-Za-z\d@$!%*?&_\-#]{8,12}$/',
            ],
            'password_confirmation' => ['required', 'string'],
            'confirm_password' => ['sometimes', 'nullable', 'same:password'],
        ], [
            'email.required' => 'L adresse email est obligatoire.',
            'email.email' => 'Adresse email invalide.',
            'token.required' => 'Le token est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.regex' => 'Le mot de passe doit contenir entre 8 et 12 caracteres, une majuscule, une minuscule, un chiffre et un caractere special.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
            'confirm_password.same' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $email = Str::lower($validated['email']);
        $resetToken = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $resetToken || ! $this->tokenIsValid($validated['token'], $resetToken)) {
            throw ValidationException::withMessages([
                'token' => ['Token invalide ou expire.'],
            ])->status(422);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'token' => ['Token invalide ou expire.'],
            ])->status(422);
        }

        DB::transaction(function () use ($user, $email, $validated): void {
            $user->password = $validated['password'];
            $user->save();

            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        Mail::to($user->email)->send(new PasswordChangedMail($user));

        return response()->json([
            'success' => true,
            'message' => 'Votre mot de passe a ete reinitialise.',
        ]);
    }

    private function forgotPasswordThrottleKey(Request $request, string $email): string
    {
        return 'forgot-password:'.$email.'|'.$request->ip();
    }

    private function resetUrl(string $plainToken, string $email): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return $frontendUrl.'/reset-password?'.http_build_query([
            'token' => $plainToken,
            'email' => $email,
        ]);
    }

    private function tokenIsValid(string $plainToken, object $resetToken): bool
    {
        $createdAt = Carbon::parse($resetToken->created_at);

        return $createdAt->addMinutes(self::RESET_TOKEN_TTL_MINUTES)->isFuture()
            && Hash::check($plainToken, $resetToken->token);
    }
}
