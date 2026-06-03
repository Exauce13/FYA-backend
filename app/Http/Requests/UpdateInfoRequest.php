<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateInfoRequest extends FormRequest
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
            'password' =>['sometimes','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_\-#])[A-Za-z\d@$!%*?&_\-#]{8,12}$/'],
            'telephone' => ['sometimes', 'max:10', 'unique:users,telephone',  'regex:/^01[4569][0-9]{7}$/'],
            'ville' => ['sometimes', 'regex:/^[A-Za-zÀ-ÿ-]{2,50}$/'],
            'quartier' => ['sometimes', 'regex:/^[A-Za-zÀ-ÿ-]{2,50}$/'],
            'annees_experiences' => ['sometimes_if:statut,artisans', 'nullable', 'integer', 'min:0'],
            'nom_association' => ['nullable', 'string', 'max:100'],
            'telephone_association' => ['nullable', 'max:10', 'regex:/^01[4569][0-9]{7}$/'],
            'diplome' => ['sometimes','file','mimes:pdf','max:5120'],
        ];
    }
    public function messages()
    {
        return[
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.regex' => 'Le mot de passe doit contenir entre 8 et 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.',
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.max' => 'Le numéro de téléphone ne doit pas dépasser 10 chiffres.',
            'telephone.regex' => 'Veuillez entrer un numéro béninois valide commençant par 01.',
            'telephone.unique' => 'Cet telephone est déjà utilisée.',
            'ville.required' => 'La ville est obligatoire.',
            'ville.regex' => 'La ville doit contenir uniquement des lettres et des tirets.',
            'quartier.required' => 'Le quartier est obligatoire.',
            'quartier.regex' => 'Le quartier doit contenir uniquement des lettres et des tirets.',
            'annees_experiences.required_if' => 'Les années d’expérience sont obligatoires pour un artisan.',
            'diplome.file' => 'Le devis doit être un fichier.',
            'diplome.mimes' => 'Le devis doit être un fichier PDF, DOC ou DOCX.',
            'diplome.max' => 'Le devis ne doit pas dépasser 5 Mo.',
        ];
    }
}
