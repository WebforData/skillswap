<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class OffreCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie_id' => ['required', 'integer', 'exists:categories,id'],
            'titre' => ['required', 'string', 'min:5', 'max:200'],
            'competence' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['required', 'string', 'min:20'],
            'niveau' => ['required', 'in:debutant,intermediaire,avance'],
            // RG02 : Durée strictement 1 ou 2 heures
            'duree' => ['required', 'integer', 'in:1,2'],
            'mode' => ['required', 'in:presentiel,distanciel'],
            'statut' => ['nullable', 'in:brouillon,publiee'],
        ];
    }

    public function messages(): array
    {
        return [
            'categorie_id.required' => "La catégorie est obligatoire.",
            'categorie_id.exists' => "La catégorie sélectionnée n'existe pas.",
            'titre.required' => "Le titre de l'offre est obligatoire.",
            'titre.min' => "Le titre doit comporter au moins 5 caractères.",
            'competence.required' => "La compétence enseignée est obligatoire.",
            'description.required' => "La description de l'offre est obligatoire.",
            'description.min' => "La description doit comporter au moins 20 caractères.",
            'niveau.in' => "Le niveau doit être 'debutant', 'intermediaire' ou 'avance'.",
            'duree.required' => "La durée de la séance est obligatoire (RG02).",
            'duree.in' => "La durée de la séance doit être strictement de 1 ou 2 heures (RG02).",
            'mode.in' => "Le mode doit être 'presentiel' ou 'distanciel'.",
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Les données de l\'offre sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
