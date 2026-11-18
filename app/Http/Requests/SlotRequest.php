<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'date_debut' => ['required', 'date', 'after:now'],
            'lieu_ou_lien' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_debut.after' => "Le créneau doit être planifié à une date et heure futures (RG04).",
            'lieu_ou_lien.required' => "Veuillez préciser le lieu physique ou le lien de réunion privée (RG19).",
        ];
    }
}
