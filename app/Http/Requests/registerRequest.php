<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class registerRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        if ($this->route('statut')) {
            $this->merge([
                'statut' => $this->route('statut'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        $isArtisan = $this->input('statut') === 'artisans';

        return [
            'name' => ['required','max:255', "regex:/^[A-Za-zÀ-ÿ]+(?:[ '\-][A-Za-zÀ-ÿ]+)*$/"],
            'email' => ['required','email', 'unique:users,email'],
            'password' =>['required','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_\-#])[A-Za-z\d@$!%*?&_\-#]{8,12}$/'],
            'telephone' => ['required', 'max:10', 'unique:users,telephone',  'regex:/^01[4569][0-9]{7}$/'],
            'statut' => ['required', 'in:clients,artisans'],
            'ville' => ['required_if:statut,artisans', 'nullable', 'regex:/^[A-Za-zÀ-ÿ-]{2,50}$/'],
            'quartier' => ['required_if:statut,artisans', 'nullable', 'regex:/^[A-Za-zÀ-ÿ-]{2,50}$/'],
            'metier_id' => [
                'nullable',
                'integer',
                'exists:metiers,id',
                $isArtisan ? 'required_without:metier_nom' : 'nullable',
            ],
            'metier_nom' => [
                'nullable',
                'string',
                'max:255',
                'exists:metiers,nom',
                $isArtisan ? 'required_without:metier_id' : 'nullable',
            ],
            'bio' => ['nullable', 'max:1000', 'string', 'regex:/^[A-Za-zÀ-ÿ0-9\s,\.\'\-\!\?]+$/'],
            'npi' => ['required_if:statut,artisans', 'nullable', 'digits_between:1,10', 'unique:artisans,npi'],
            'annees_experiences' => ['required_if:statut,artisans', 'nullable', 'integer', 'min:0'],
            'nom_association' => ['nullable', 'string', 'max:100'],
            'telephone_association' => ['nullable', 'max:10', 'regex:/^01[4569][0-9]{7}$/'],
            'diplome' => ['nullable', ],
        ];
    }
    public function messages()
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'name.regex' => 'Le nom doit contenir uniquement des lettres, espaces ou tirets.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Veuillez entrer une adresse email valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.regex' => 'Le mot de passe doit contenir entre 8 et 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.',
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.max' => 'Le numéro de téléphone ne doit pas dépasser 10 chiffres.',
            'telephone.regex' => 'Veuillez entrer un numéro béninois valide commençant par 01.',
            'telephone.unique' => 'Cet telephone est déjà utilisée.',
            'statut.required' => 'Le rôle est obligatoire.',
            'statut.in' => 'Le rôle choisi est invalide.',
            'ville.required' => 'La ville est obligatoire.',
            'ville.regex' => 'La ville doit contenir uniquement des lettres et des tirets.',
            'quartier.required' => 'Le quartier est obligatoire.',
            'quartier.regex' => 'Le quartier doit contenir uniquement des lettres et des tirets.',
            'metier_id.required_if' => 'Le métier est obligatoire pour un artisan.',
            'metier_id.required_without' => 'Le métier est obligatoire.',
            'metier_id.integer' => 'Le métier choisi est invalide.',
            'metier_id.exists' => 'Le métier choisi est invalide.',
            'metier_nom.required_without' => 'Le métier est obligatoire.',
            'metier_nom.string' => 'Le métier choisi est invalide.',
            'metier_nom.exists' => 'Le métier choisi est invalide.',
            #'bio.required_if' => 'La bio est obligatoire pour un artisan.',
            'bio.regex' => 'La bio doit contenir uniquement des lettres, espaces ou tirets ',
            'npi.digits_between' => 'Le npi ne doit pas dépasser 10 chiffres.',
            'npi.required_if' => 'Le npi est obligatoire pour un artisan.',
            'npi.unique'=> 'le npi est unique',
            'annees_experiences.required_if' => 'Les années d’expérience sont obligatoires pour un artisan.',
            'telephone_association.regex' => 'Veuillez entrer un numéro d’association béninois valide commençant par 01.',

        ];
    }
}
