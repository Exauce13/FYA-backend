<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PostRequest extends FormRequest
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
            'description' => [ 'nullable', 'string', 'max:1000'],
            'media_json' => ['nullable','array','max:10'],
            'media_json.*' => ['file','mimes:jpg,jpeg,png,webp,mp4,mov','max:51200'],
            'post_type' => ['required','in:realisations,services,promotion'],
        ];
    }

    public function messages(): array
    {
        return [

            'post_type.required' => 'Le type de publication est obligatoire.',
            'post_type.in' => 'Le type de publication est invalide.',
            'media_json.array' => 'Les médias doivent être envoyés sous forme de tableau.',
            'media_json.max' => 'Vous ne pouvez pas envoyer plus de 10 fichiers.',
            'media_json.*.mimes' => 'Le format du fichier est invalide.',
            'media_json.*.max' => 'Un fichier dépasse la taille autorisée.',
        ];
    }
}


