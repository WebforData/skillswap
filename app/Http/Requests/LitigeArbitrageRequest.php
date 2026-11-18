<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LitigeArbitrageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                'in:transfert_intervenant,transferer_intervenant,restitution_beneficiaire,restituer_beneficiaire',
            ],
            'motif' => [
                'required',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision arbitrale est obligatoire.',
            'decision.in' => 'La décision doit être soit "transfert_intervenant" soit "restitution_beneficiaire" (RG13).',
            'motif.required' => 'Le motif de la décision arbitrale est obligatoire.',
            'motif.min' => 'Le motif doit comporter au moins 5 caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser 2000 caractères.',
        ];
    }
}
