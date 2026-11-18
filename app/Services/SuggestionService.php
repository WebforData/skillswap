<?php

namespace App\Services;

use App\Models\User;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Collection;

class SuggestionService
{
    /**
     * Proposer des offres correspondant aux compétences recherchées dans le profil.
     * Correspondance sur la compétence ou la catégorie.
     * Uniquement les offres publiées d'autres étudiants avec au moins un créneau futur disponible.
     */
    public function getSuggestionsForUser(User $user, int $limit = 6): Collection
    {
        // Récupérer les libellés de compétences recherchées par l'étudiant
        $skillsRecherchees = $user->skillsRecherchees()->pluck('competence')->toArray();

        if (empty($skillsRecherchees)) {
            // Si l'étudiant n'a pas encore renseigné de compétences recherchées, proposer les offres les plus récentes
            return Offer::activePublic()
                ->where('user_id', '!=', $user->id)
                ->whereHas('availableSlots')
                ->with(['user', 'category', 'availableSlots'])
                ->latest()
                ->take($limit)
                ->get();
        }

        return Offer::activePublic()
            ->where('user_id', '!=', $user->id)
            ->whereHas('availableSlots')
            ->where(function ($query) use ($skillsRecherchees) {
                // Correspondance directe sur le titre ou la compétence
                foreach ($skillsRecherchees as $skill) {
                    $query->orWhere('competence', 'LIKE', "%{$skill}%")
                          ->orWhere('titre', 'LIKE', "%{$skill}%")
                          ->orWhereHas('category', function ($catQ) use ($skill) {
                              $catQ->where('nom', 'LIKE', "%{$skill}%");
                          });
                }
            })
            ->with(['user', 'category', 'availableSlots'])
            ->latest()
            ->take($limit)
            ->get();
    }
}
