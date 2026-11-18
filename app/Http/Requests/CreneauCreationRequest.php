<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreneauCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
            'lieu_public' => ['required', 'string', 'max:255'],
            'lien_ou_lieu_prive' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => "La date du créneau est obligatoire.",
            'date.after_or_equal' => "Le créneau ne peut pas être planifié dans le passé (RG04).",
            'heure_debut.required' => "L'heure de début est obligatoire.",
            'heure_debut.date_format' => "L'heure de début doit respecter le format HH:MM.",
            'heure_fin.required' => "L'heure de fin est obligatoire.",
            'heure_fin.date_format' => "L'heure de fin doit respecter le format HH:MM.",
            'lieu_public.required' => "Le lieu public indicatif est obligatoire.",
            'lien_ou_lieu_prive.required' => "Le lien de visioconférence ou le lieu privé exact est obligatoire (RG19).",
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Les informations du créneau sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
