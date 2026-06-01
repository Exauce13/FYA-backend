<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class PhotoUpdateRequest extends FormRequest
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
            'photo' => ['required','image','mimes:jpg,jpeg,png,webp','max:5048'],
        ];
    }
    public function messages(){
        return [
            'photo.required' => 'La photo de profil est obligatoire.',
            'photo.image' => 'Le fichier doit être une image.',
            'photomimes' => 'La photo doit être au format jpg, jpeg, png ou webp.',
            'photo.max' => 'La taille de la photo ne doit pas dépasser 5 Mo.',
        ];
    }
}
