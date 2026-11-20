<?php

namespace App\Services;

use App\Exceptions\AdminException;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminUserService
{
    /**
     * Liste paginée des utilisateurs avec filtres et recherche.
     *
     * @param  array  $filtres  ['q', 'role', 'statut']
     */
    public function listerUtilisateurs(array $filtres = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('portefeuille')
            ->withCount([
                'offres',
                'reservationsBeneficiaire as reservations_demandees_count',
            ]);

        // Recherche textuelle (nom, prénom, email, filière)
        if (! empty($filtres['q'])) {
            $q = trim($filtres['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('nom', 'LIKE', "%{$q}%")
                    ->orWhere('prenom', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('filiere', 'LIKE', "%{$q}%");
            });
        }

        // Filtre par rôle ('etudiant', 'administrateur')
        if (! empty($filtres['role'])) {
            $query->where('role', $filtres['role']);
        }

        // Filtre par statut ('actif', 'desactive')
        if (! empty($filtres['statut'])) {
            $query->where('statut', $filtres['statut']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Consulte le profil complet d'un utilisateur avec statistiques d'activité.
     */
    public function getUtilisateur(int $id): User
    {
        return User::with(['portefeuille'])
            ->withCount([
                'offres',
                'reservationsBeneficiaire as reservations_demandees_count',
                'evaluationsRecues as evaluations_count',
            ])
            ->findOrFail($id);
    }

    /**
     * Désactive un compte utilisateur (RG18).
     *
     *
     * @throws AdminException
     */
    public function desactiverCompte(User $admin, int $cibleId): User
    {
        return DB::transaction(function () use ($admin, $cibleId) {
            $cible = User::where('id', $cibleId)->lockForUpdate()->firstOrFail();

            // Interdire à un administrateur de désactiver son propre compte
            if ((int) $admin->id === (int) $cible->id) {
                throw new AdminException('Vous ne pouvez pas désactiver votre propre compte administrateur.', Response::HTTP_FORBIDDEN);
            }

            if ($cible->statut === 'desactive') {
                return $cible; // Idempotent
            }

            // 1. Marquer le compte comme désactivé
            $cible->statut = 'desactive';
            $cible->save();

            // 2. RG18 : Annuler automatiquement les demandes en attente envoyées par l'étudiant
            Reservation::where('beneficiaire_id', $cible->id)
                ->where('statut', 'en_attente')
                ->lockForUpdate()
                ->get()->each(fn ($reservation) => $reservation->forceFill([
                    'statut' => 'annulee',
                    'motif_refus' => "Demande annulée suite à la désactivation du compte par l'administration (RG18).",
                    'updated_at' => now(),
                ])->save());

            // 3. RG18 : Annuler automatiquement les demandes en attente reçues sur les offres de l'étudiant
            Reservation::whereHas('creneau.offre', fn ($q) => $q->where('etudiant_id', $cible->id))
                ->where('statut', 'en_attente')
                ->lockForUpdate()
                ->get()->each(fn ($reservation) => $reservation->forceFill([
                    'statut' => 'annulee',
                    'motif_refus' => "Demande annulée : le compte de l'intervenant a été désactivé par l'administration (RG18).",
                    'updated_at' => now(),
                ])->save());

            // SÉCURITÉ FINANCIÈRE : Les réservations déjà acceptées (acceptee, realisee, en_litige) sont STRICTEMENT
            // préservées, et les crédits bloqués demeurent intacts sous séquestre sans mouvement automatique.

            return $cible->fresh(['portefeuille']);
        });
    }

    /**
     * Réactive un compte utilisateur.
     */
    public function activerCompte(User $admin, int $cibleId): User
    {
        return DB::transaction(function () use ($cibleId) {
            $cible = User::where('id', $cibleId)->lockForUpdate()->firstOrFail();

            if ($cible->statut === 'actif') {
                return $cible; // Idempotent
            }

            $cible->statut = 'actif';
            $cible->save();

            return $cible->fresh(['portefeuille']);
        });
    }
}
