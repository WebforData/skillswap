<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LitigeCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'La description du litige est obligatoire.',
            'description.min' => 'La description doit comporter au moins 10 caractères.',
            'description.max' => 'La description ne peut pas dépasser 2000 caractères.',
        ];
    }
}
