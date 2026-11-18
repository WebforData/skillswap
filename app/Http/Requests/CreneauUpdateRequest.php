<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreneauUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'heure_debut' => ['sometimes', 'required', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'required', 'date_format:H:i'],
            'lieu_public' => ['sometimes', 'required', 'string', 'max:255'],
            'lien_ou_lieu_prive' => ['sometimes', 'required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => "Le créneau ne peut pas être planifié dans le passé (RG04).",
            'heure_debut.date_format' => "L'heure de début doit respecter le format HH:MM.",
            'heure_fin.date_format' => "L'heure de fin doit respecter le format HH:MM.",
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Les modifications du créneau sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
