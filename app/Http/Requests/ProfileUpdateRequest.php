<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $userId = Auth::id();
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', "unique:users,email,{$userId}"],
            'filiere' => ['nullable', 'string', 'max:150'],
            'niveau_etudes' => ['nullable', 'string', 'max:50'],
            'skills_proposees' => ['nullable', 'string'],
            'skills_recherchees' => ['nullable', 'string'],
        ];
    }
}
