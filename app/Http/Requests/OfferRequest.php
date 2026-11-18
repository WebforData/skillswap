<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'competence' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'min:20'],
            'niveau' => ['required', 'in:debutant,intermediaire,avance'],
            // RG02: Durée obligatoire de 1 ou 2 heures
            'duree' => ['required', 'integer', 'in:1,2'],
            'mode' => ['required', 'in:presentiel,distanciel'],
            'status' => ['required', 'in:brouillon,publiee,archivee'],
        ];
    }

    public function messages(): array
    {
        return [
            'duree.in' => "La durée d'une séance doit être exactement d'une ou deux heures (RG02).",
            'description.min' => "Veuillez fournir une description détaillée d'au moins 20 caractères.",
        ];
    }
}
