<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryCreationRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Services\AdminCategoryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminCategoryController extends Controller
{
    public function __construct(
        protected AdminCategoryService $adminCategoryService
    ) {}

    /**
     * Liste toutes les catégories avec le nombre d'offres rattachées.
     */
    public function index(): JsonResponse
    {
        $categories = $this->adminCategoryService->listerCategories();

        return response()->json([
            'total' => $categories->count(),
            'categories' => $categories,
        ], Response::HTTP_OK);
    }

    /**
     * Crée une nouvelle catégorie de compétences.
     */
    public function store(CategoryCreationRequest $request): JsonResponse
    {
        $categorie = $this->adminCategoryService->creerCategorie($request->validated());

        return response()->json([
            'message' => 'Catégorie créée avec succès.',
            'categorie' => $categorie,
        ], Response::HTTP_CREATED);
    }

    /**
     * Met à jour une catégorie.
     */
    public function update(CategoryUpdateRequest $request, int $id): JsonResponse
    {
        $categorie = $this->adminCategoryService->modifierCategorie($id, $request->validated());

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès.',
            'categorie' => $categorie,
        ], Response::HTTP_OK);
    }

    /**
     * Supprime une catégorie (protégée contre la suppression si des offres existent).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->adminCategoryService->supprimerCategorie($id);

        return response()->json([
            'message' => 'Catégorie supprimée avec succès.',
        ], Response::HTTP_OK);
    }
}
