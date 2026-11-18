<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('category');

        return [
            'nom' => ['required', 'string', 'min:2', 'max:100', 'unique:categories,nom,' . $id],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la catégorie est obligatoire.',
            'nom.min' => 'Le nom doit comporter au moins 2 caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.unique' => 'Ce nom de catégorie est déjà utilisé.',
        ];
    }
}
