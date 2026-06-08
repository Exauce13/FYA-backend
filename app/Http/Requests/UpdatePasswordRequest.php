<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_\-#])[A-Za-z\d@$!%*?&_\-#]{8,12}$/'],
            'new_password_confirmation' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => 'L ancien mot de passe est obligatoire.',
            'old_password.string' => 'L\'ancien mot de passe est invalide.',
            'new_password.required' => 'Le nouveau mot de passe est obligatoire.',
            'new_password.string' => 'Le nouveau mot de passe est invalide.',
            'new_password.regex' => 'Le mot de passe doit contenir entre 8 et 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.',
            'new_password_confirmation.string' => 'La confirmation du nouveau mot de passe est invalide.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => true,
            'message' => 'Erreur de validation',
            'errorlist' => $validator->errors(),
        ], 422));
    }
}
