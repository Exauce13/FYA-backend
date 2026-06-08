<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AppelOffresRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => true,
            'message'=> 'Erreur de validation',
            'errorlist'=> $validator->errors()
        ]));
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
             'titre' => ['required','string','max:255'],
             'description' => ['required','string','min:10','max:1000'],
             'metier_id' => ['nullable', 'integer', 'exists:metiers,id', 'required_without:metier_nom'],
             'metier_nom' => ['nullable', 'string', 'max:255', 'exists:metiers,nom', 'required_without:metier_id'],
             'ville' => ['required','string','max:255'],
             'budget' => ['nullable','integer','min:0'],
             'appel_json' => ['nullable','array','max:10'],
             'appel_json.*' => ['file','mimes:jpg,jpeg,png,webp,mp4,mov','max:51200'],
             'media_json' => ['nullable','array','max:10'],
             'media_json.*' => ['file','mimes:jpg,jpeg,png,webp,mp4,mov','max:51200'],
             'status' => ['nullable','in:open,closed'],
        ];
    }

    /**
     * Messages personnalisés.
     */
    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'titre.string' => 'Le titre doit être une chaîne de caractères valide.',
            'titre.max' => 'Le titre ne doit pas dépasser 255 caractères.',
            'description.required' => 'La description est obligatoire.',
            'description.min' => 'La description doit contenir au moins 10 caractères.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'metier_id.required_without' => 'Le métier ciblé est obligatoire.',
            'metier_id.integer' => 'Le métier ciblé est invalide.',
            'metier_id.exists' => 'Le métier ciblé est invalide.',
            'metier_nom.required_without' => 'Le métier ciblé est obligatoire.',
            'metier_nom.string' => 'Le métier ciblé est invalide.',
            'metier_nom.exists' => 'Le métier ciblé est invalide.',
            'ville.required' => 'La ville est obligatoire.',
            'ville.string' => 'La ville doit être une chaîne de caractères valide.',
            'ville.max' => 'La ville ne doit pas dépasser 255 caractères.',
            'budget.integer' => 'Le budget doit être un nombre entier valide.',
            'budget.min' => 'Le budget ne peut pas être négatif.',
            'appel_json.array' => 'Les médias doivent être envoyés sous forme de tableau.',
            'appel_json.max' => 'Vous ne pouvez pas envoyer plus de 10 fichiers.',
            'appel_json.*.mimes' => 'Le format du fichier est invalide.',
            'appel_json.*.max' => 'Un fichier dépasse la taille autorisée.',
            'media_json.array' => 'Les médias doivent être envoyés sous forme de tableau.',
            'media_json.max' => 'Vous ne pouvez pas envoyer plus de 10 fichiers.',
            'media_json.*.mimes' => 'Le format du fichier est invalide.',
            'media_json.*.max' => 'Un fichier dépasse la taille autorisée.',
        ];
    }
}
