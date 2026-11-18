<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'creneau_id' => ['required', 'integer', 'exists:creneaux,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'creneau_id.required' => 'Le créneau horaire est obligatoire.',
            'creneau_id.exists' => 'Le créneau horaire sélectionné est introuvable.',
        ];
    }
}
