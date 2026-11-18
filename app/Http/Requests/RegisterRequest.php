<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à effectuer cette requête.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour l'inscription d'un étudiant.
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'filiere' => ['nullable', 'string', 'max:100'],
            'competences_proposees' => ['nullable', 'string'],
            'competences_recherchees' => ['nullable', 'string'],
        ];
    }

    /**
     * Messages de validation personnalisés.
     */
    public function messages(): array
    {
        return [
            'nom.required' => "Le nom est obligatoire.",
            'prenom.required' => "Le prénom est obligatoire.",
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "L'adresse email doit être valide.",
            'email.unique' => "Cette adresse électronique est déjà associée à un compte existant (RG01).",
            'password.required' => "Le mot de passe est obligatoire.",
            'password.min' => "Le mot de passe doit comporter au moins 8 caractères.",
            'password.confirmed' => "La confirmation du mot de passe ne correspond pas.",
        ];
    }
}
