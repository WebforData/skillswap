<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount(['offers', 'skills'])->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:100', 'unique:categories,nom'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        Category::create([
            'nom' => $request->nom,
            'slug' => Str::slug($request->nom),
            'description' => $request->description,
            'icon' => $request->icon ?? 'bi-tag',
        ]);

        return back()->with('success', "La catégorie '{$request->nom}' a été créée.");
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:100', 'unique:categories,nom,' . $category->id],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category->update([
            'nom' => $request->nom,
            'slug' => Str::slug($request->nom),
            'description' => $request->description,
            'icon' => $request->icon ?? $category->icon,
        ]);

        return back()->with('success', "La catégorie a été mise à jour.");
    }

    public function destroy(Category $category)
    {
        if ($category->offers()->count() > 0) {
            return back()->with('error', "Impossible de supprimer cette catégorie car des offres de compétences y sont actuellement rattachées.");
        }

        $category->delete();

        return back()->with('success', "La catégorie a été supprimée.");
    }
}
