<?php

namespace App\Services;

use App\Models\Offre;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminOffreService
{
    public function __construct(
        protected OffreService $offreService
    ) {}

    /**
     * Liste paginée des offres pour le back-office avec recherche et filtres complets.
     *
     * @param  array  $filtres ['q', 'categorie_id', 'statut', 'niveau', 'mode']
     * @param  int    $perPage
     * @return LengthAwarePaginator
     */
    public function listerOffres(array $filtres = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Offre::with(['intervenant', 'categorie'])
            ->withCount(['creneaux']);

        // Recherche par compétence, titre, description ou nom/prénom intervenant
        if (!empty($filtres['q'])) {
            $q = trim($filtres['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('titre', 'LIKE', "%{$q}%")
                    ->orWhere('competence', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%")
                    ->orWhereHas('intervenant', function ($userQuery) use ($q) {
                        $userQuery->where('nom', 'LIKE', "%{$q}%")
                            ->orWhere('prenom', 'LIKE', "%{$q}%")
                            ->orWhere('email', 'LIKE', "%{$q}%");
                    });
            });
        }

        // Filtre par catégorie
        if (!empty($filtres['categorie_id'])) {
            $query->where('categorie_id', (int) $filtres['categorie_id']);
        }

        // Filtre par statut ('brouillon', 'publiee', 'archivee')
        if (!empty($filtres['statut'])) {
            $query->where('statut', $filtres['statut']);
        }

        // Filtre par niveau
        if (!empty($filtres['niveau'])) {
            $query->where('niveau', $filtres['niveau']);
        }

        // Filtre par mode
        if (!empty($filtres['mode'])) {
            $query->where('mode', $filtres['mode']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Consulte les détails exhaustifs d'une offre pour modération.
     *
     * @param  int  $id
     * @return Offre
     */
    public function getOffre(int $id): Offre
    {
        return Offre::with([
            'intervenant.portefeuille',
            'categorie',
            'creneaux.reservations',
        ])->findOrFail($id);
    }

    /**
     * Archive/retire une offre du catalogue (RG17).
     * Les demandes en attente sont annulées, les réservations acceptées et crédits sont préservés.
     *
     * @param  User  $admin
     * @param  int   $id
     * @return Offre
     */
    public function archiverOffre(User $admin, int $id): Offre
    {
        return $this->offreService->archiverOffre($admin, $id);
    }
}
