<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class EvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer'],
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_id.required' => "L'identifiant de la réservation est obligatoire.",
            'reservation_id.integer' => "L'identifiant de la réservation doit être un entier valide.",
            'note.required' => "La note est obligatoire.",
            'note.integer' => "La note doit être un nombre entier.",
            'note.between' => "La note doit être comprise entre 1 et 5 étoiles (RG15).",
            'commentaire.max' => "Le commentaire ne peut pas dépasser 1000 caractères.",
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Les données transmises sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
