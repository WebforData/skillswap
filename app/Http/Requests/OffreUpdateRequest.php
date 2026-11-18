<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class OffreUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'titre' => ['sometimes', 'required', 'string', 'min:5', 'max:200'],
            'competence' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'required', 'string', 'min:20'],
            'niveau' => ['sometimes', 'required', 'in:debutant,intermediaire,avance'],
            'duree' => ['sometimes', 'required', 'integer', 'in:1,2'],
            'mode' => ['sometimes', 'required', 'in:presentiel,distanciel'],
            'statut' => ['sometimes', 'required', 'in:brouillon,publiee,archivee'],
        ];
    }

    public function messages(): array
    {
        return [
            'categorie_id.exists' => "La catégorie sélectionnée n'existe pas.",
            'titre.min' => "Le titre doit comporter au moins 5 caractères.",
            'description.min' => "La description doit comporter au moins 20 caractères.",
            'duree.in' => "La durée de la séance doit être strictement de 1 ou 2 heures (RG02).",
            'niveau.in' => "Le niveau doit être 'debutant', 'intermediaire' ou 'avance'.",
            'mode.in' => "Le mode doit être 'presentiel' ou 'distanciel'.",
            'statut.in' => "Le statut doit être 'brouillon', 'publiee' ou 'archivee'.",
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Les modifications de l\'offre sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
