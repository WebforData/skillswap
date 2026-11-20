<?php

namespace App\Services;

use App\Models\Offre;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SuggestionService
{
    public function getSuggestionsForUser(User $user, int $limit = 6): Collection
    {
        $skills = preg_split('/[,;\n]+/u', $user->competences_recherchees ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $skills = array_values(array_filter(array_map('trim', $skills)));
        if (! $skills) {
            return new Collection;
        }

        return Offre::where('statut', 'publiee')->where('etudiant_id', '!=', $user->id)
            ->whereHas('intervenant', fn ($q) => $q->where('statut', 'actif'))
            ->whereHas('creneaux', fn ($q) => $q->futurs()->disponibles())
            ->where(function ($q) use ($skills) {
                foreach ($skills as $skill) {
                    $q->orWhere('competence', 'LIKE', "%$skill%")
                        ->orWhereHas('categorie', fn ($cat) => $cat->where('nom', 'LIKE', "%$skill%"));
                }
            })->with(['categorie', 'intervenant:id,nom,prenom,filiere'])->latest()->limit($limit)->get();
    }
}
