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
             'description' => ['required','string','min:10','max:1000'],
             'metiers_cibles' => ['required','string','max:255'],
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
            'description.required' => 'La description est obligatoire.',
            'description.min' => 'La description doit contenir au moins 10 caractères.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'metiers_cibles.required' => 'Le métier ciblé est obligatoire.',
            'media_json.array' => 'Les médias doivent être envoyés sous forme de tableau.',
            'media_json.max' => 'Vous ne pouvez pas envoyer plus de 10 fichiers.',
            'media_json.*.mimes' => 'Le format du fichier est invalide.',
            'media_json.*.max' => 'Un fichier dépasse la taille autorisée.',
        ];
    }
}

