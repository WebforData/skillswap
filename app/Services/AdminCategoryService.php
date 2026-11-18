<?php

namespace App\Services;

use App\Exceptions\CategorieNonSupprimableException;
use App\Models\Categorie;
use Illuminate\Database\Eloquent\Collection;

class AdminCategoryService
{
    /**
     * Liste toutes les catégories avec le nombre d'offres associées.
     *
     * @return Collection
     */
    public function listerCategories(): Collection
    {
        return Categorie::withCount('offres')
            ->orderBy('nom', 'asc')
            ->get();
    }

    /**
     * Crée une nouvelle catégorie.
     *
     * @param  array  $data
     * @return Categorie
     */
    public function creerCategorie(array $data): Categorie
    {
        return Categorie::create([
            'nom' => trim($data['nom']),
        ]);
    }

    /**
     * Met à jour une catégorie existante.
     *
     * @param  int    $id
     * @param  array  $data
     * @return Categorie
     */
    public function modifierCategorie(int $id, array $data): Categorie
    {
        $categorie = Categorie::findOrFail($id);
        $categorie->nom = trim($data['nom']);
        $categorie->save();

        return $categorie;
    }

    /**
     * Supprime une catégorie si aucune offre n'y est rattachée.
     *
     * @param  int  $id
     * @return bool
     *
     * @throws CategorieNonSupprimableException
     */
    public function supprimerCategorie(int $id): bool
    {
        $categorie = Categorie::withCount('offres')->findOrFail($id);

        if ($categorie->offres_count > 0 || $categorie->offres()->exists()) {
            throw new CategorieNonSupprimableException("Impossible de supprimer la catégorie '{$categorie->nom}' car {$categorie->offres_count} offre(s) y sont associées (RG04).");
        }

        return (bool) $categorie->delete();
    }
}
