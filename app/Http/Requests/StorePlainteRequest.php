<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator as LaravelValidator;

class StorePlainteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mise_en_cause_id' => ['required', 'integer', 'exists:users,id'],
            'motif' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mise_en_cause_id.required' => 'L utilisateur signalé est obligatoire.',
            'mise_en_cause_id.integer' => 'L identifiant de l utilisateur signalé doit être un entier valide.',
            'mise_en_cause_id.exists' => 'L utilisateur signalé est introuvable.',
            'motif.required' => 'Le motif du signalement est obligatoire.',
            'motif.string' => 'Le motif doit être une chaîne de caractères valide.',
            'motif.max' => 'Le motif ne peut pas dépasser 255 caractères.',
            'description.string' => 'La description doit être une chaîne de caractères valide.',
            'description.max' => 'La description ne peut pas dépasser 5000 caractères.',
        ];
    }

    protected function withValidator(LaravelValidator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            if (! $user) {
                return;
            }

            if ((int) $this->input('mise_en_cause_id') === (int) $user->id) {
                $validator->errors()->add(
                    'mise_en_cause_id',
                    'Vous ne pouvez pas vous signaler vous-même.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Erreur de validation.',
            'data' => null,
            'errors' => $validator->errors(),
        ], 422));
    }
}
