<?php

namespace App\Services;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\DureeOffreIncompatibleException;
use App\Exceptions\OffreNonAutoriseeException;
use App\Exceptions\OffreStatutInvalideException;
use App\Models\Creneau;
use App\Models\Offre;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OffreService
{
    /**
     * Crée une nouvelle offre de compétence pour un intervenant (RG04).
     *
     *
     * @throws CompteDesactiveException
     */
    public function creerOffre(User $intervenant, array $data): Offre
    {
        // RG18 : Seul un compte actif peut créer une offre
        if (! $intervenant->isActif()) {
            throw new CompteDesactiveException('Votre compte est désactivé (RG18). Impossible de créer une offre.');
        }

        $offre = new Offre;
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => (int) $data['categorie_id'],
            'titre' => trim($data['titre']),
            'competence' => trim($data['competence']),
            'description' => trim($data['description']),
            'niveau' => $data['niveau'] ?? 'debutant',
            'duree' => (int) ($data['duree'] ?? 1),
            'mode' => $data['mode'] ?? 'presentiel',
            'statut' => $data['statut'] ?? 'brouillon',
        ]);
        $offre->save();

        return $offre->fresh(['categorie', 'intervenant']);
    }

    /**
     * Modifie une offre existante (propriétaire ou administrateur).
     *
     *
     * @throws CompteDesactiveException
     * @throws OffreNonAutoriseeException
     * @throws OffreStatutInvalideException
     * @throws DureeOffreIncompatibleException
     */
    public function modifierOffre(User $intervenant, int $offreId, array $data): Offre
    {
        if (! $intervenant->isActif()) {
            throw new CompteDesactiveException('Votre compte est désactivé (RG18). Impossible de modifier une offre.');
        }

        return DB::transaction(function () use ($intervenant, $offreId, $data) {
            $offre = Offre::where('id', $offreId)->lockForUpdate()->firstOrFail();

            // Vérification des droits : Seul le propriétaire ou un administrateur peut modifier
            if (! $intervenant->isAdministrateur() && (int) $offre->etudiant_id !== (int) $intervenant->id) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à modifier cette offre.");
            }

            // RG17 : Une offre archivée ne peut plus être modifiée
            if ($offre->statut === 'archivee') {
                throw new OffreStatutInvalideException('Une offre archivée ne peut plus être modifiée (RG17).');
            }

            // RG02 : Vérification de la compatibilité de durée avec les créneaux existants
            if (isset($data['duree']) && (int) $data['duree'] !== (int) $offre->duree) {
                $nouvelleDureeMinutes = (int) $data['duree'] * 60;
                $creneauxIncompatibles = Creneau::where('offre_id', $offre->id)->get()->filter(function ($creneau) use ($nouvelleDureeMinutes) {
                    $dureeReelle = Carbon::parse($creneau->heure_debut)->diffInMinutes(Carbon::parse($creneau->heure_fin));

                    return $dureeReelle !== $nouvelleDureeMinutes;
                });

                if ($creneauxIncompatibles->isNotEmpty()) {
                    throw new DureeOffreIncompatibleException("Impossible de modifier la durée de l'offre : des créneaux existants deviendraient incompatibles (RG02).");
                }

                $offre->duree = (int) $data['duree'];
            }

            if (isset($data['categorie_id'])) {
                $offre->categorie_id = (int) $data['categorie_id'];
            }
            if (isset($data['titre'])) {
                $offre->titre = trim($data['titre']);
            }
            if (isset($data['competence'])) {
                $offre->competence = trim($data['competence']);
            }
            if (isset($data['description'])) {
                $offre->description = trim($data['description']);
            }
            if (isset($data['niveau'])) {
                $offre->niveau = $data['niveau'];
            }
            if (isset($data['mode'])) {
                $offre->mode = $data['mode'];
            }
            if (isset($data['statut']) && in_array($data['statut'], ['brouillon', 'publiee', 'archivee'])) {
                // Si la modification demande l'archivage, appeler la logique dédiée
                if ($data['statut'] === 'archivee') {
                    return $this->archiverOffre($intervenant, $offreId);
                }
                $offre->statut = $data['statut'];
            }

            $offre->save();

            return $offre->fresh(['categorie', 'intervenant']);
        });
    }

    /**
     * Publie une offre pour la rendre visible au catalogue (RG04).
     */
    public function publierOffre(User $intervenant, int $offreId): Offre
    {
        if (! $intervenant->isActif()) {
            throw new CompteDesactiveException('Votre compte est désactivé (RG18).');
        }

        return DB::transaction(function () use ($intervenant, $offreId) {
            $offre = Offre::where('id', $offreId)->lockForUpdate()->firstOrFail();

            if (! $intervenant->isAdministrateur() && (int) $offre->etudiant_id !== (int) $intervenant->id) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à publier cette offre.");
            }

            if ($offre->statut === 'archivee') {
                throw new OffreStatutInvalideException('Une offre archivée ne peut plus être republiée (RG17).');
            }

            $offre->statut = 'publiee';
            $offre->save();

            return $offre->fresh(['categorie', 'intervenant']);
        });
    }

    /**
     * Repasse une offre en brouillon.
     */
    public function passerEnBrouillon(User $intervenant, int $offreId): Offre
    {
        if (! $intervenant->isActif()) {
            throw new CompteDesactiveException('Votre compte est désactivé (RG18).');
        }

        return DB::transaction(function () use ($intervenant, $offreId) {
            $offre = Offre::where('id', $offreId)->lockForUpdate()->firstOrFail();

            if (! $intervenant->isAdministrateur() && (int) $offre->etudiant_id !== (int) $intervenant->id) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à modifier cette offre.");
            }

            if ($offre->statut === 'archivee') {
                throw new OffreStatutInvalideException('Une offre archivée ne peut pas être repassée en brouillon (RG17).');
            }

            $offre->statut = 'brouillon';
            $offre->save();
            Reservation::whereHas('creneau', fn ($q) => $q->where('offre_id', $offre->id))->where('statut', 'en_attente')->lockForUpdate()->get()->each(fn ($r) => $r->forceFill(['statut' => 'annulee', 'motif_refus' => 'Offre retirée du catalogue.'])->save());

            return $offre->fresh(['categorie', 'intervenant']);
        });
    }

    /**
     * Archive une offre et annule atomiquement les réservations en attente (RG17).
     *
     * Règles RG17 :
     * 1. L'offre passe au statut 'archivee' (empêche toute nouvelle réservation).
     * 2. Toutes les réservations 'en_attente' sur ses créneaux sont annulées avec motif d'audit.
     * 3. Les réservations 'acceptee', 'realisee' ou 'en_litige' sont strictement CONSERVÉES.
     * 4. Les crédits bloqués des réservations acceptées ne sont ni annulés ni perdus.
     */
    public function archiverOffre(User $utilisateur, int $offreId): Offre
    {
        if (! $utilisateur->isActif()) {
            throw new CompteDesactiveException("Votre compte est désactivé (RG18). Impossible d'archiver cette offre.");
        }

        return DB::transaction(function () use ($utilisateur, $offreId) {
            $offre = Offre::where('id', $offreId)->lockForUpdate()->firstOrFail();

            // Vérification des droits
            if (! $utilisateur->isAdministrateur() && (int) $offre->etudiant_id !== (int) $utilisateur->id) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à archiver cette offre (RG17).");
            }

            // Déjà archivée : idempotence
            if ($offre->statut === 'archivee') {
                return $offre;
            }

            // 1. Passage de l'offre à 'archivee'
            $offre->statut = 'archivee';
            $offre->save();

            // 2. Récupérer les identifiants des créneaux rattachés à cette offre
            $creneauxIds = Creneau::where('offre_id', $offre->id)->pluck('id');

            // 3. RG17 : Annuler toutes les réservations EN ATTENTE uniquement
            $demandesEnAttente = Reservation::whereIn('creneau_id', $creneauxIds)
                ->where('statut', 'en_attente')
                ->lockForUpdate()
                ->get();

            foreach ($demandesEnAttente as $demande) {
                $demande->statut = 'annulee';
                $demande->motif_refus = "Réservation annulée suite à l'archivage de l'offre #{$offre->id} par l'intervenant (RG17).";
                $demande->save();
            }

            // Les réservations 'acceptee', 'realisee', 'en_litige' restent intactes
            return $offre->fresh(['categorie', 'intervenant']);
        });
    }

    /**
     * Recherche et filtre les offres publiées du catalogue public (RG04).
     */
    public function rechercherOffres(array $filtres, int $perPage = 12): LengthAwarePaginator
    {
        $query = Offre::query()
            ->where('statut', 'publiee')
            ->whereHas('intervenant', function ($q) {
                $q->where('statut', 'actif');
            })
            ->with([
                'categorie:id,nom',
                'intervenant:id,nom,prenom,filiere',
            ]);

        // Filtre par mot-clé (titre, compétence, description)
        if (! empty($filtres['q'])) {
            $q = trim($filtres['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('titre', 'LIKE', "%{$q}%")
                    ->orWhere('competence', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        // Filtre par catégorie
        if (! empty($filtres['categorie_id'])) {
            $query->where('categorie_id', (int) $filtres['categorie_id']);
        }

        // Filtre par niveau
        if (! empty($filtres['niveau']) && in_array($filtres['niveau'], ['debutant', 'intermediaire', 'avance'])) {
            $query->where('niveau', $filtres['niveau']);
        }

        // Filtre par mode
        if (! empty($filtres['mode']) && in_array($filtres['mode'], ['presentiel', 'distanciel'])) {
            $query->where('mode', $filtres['mode']);
        }

        // Filtre par durée (1 ou 2)
        if (! empty($filtres['duree']) && in_array((int) $filtres['duree'], [1, 2])) {
            $query->where('duree', (int) $filtres['duree']);
        }

        if (! empty($filtres['date'])) {
            $query->whereHas('creneaux', fn ($q) => $q->futurs()->disponibles()->whereDate('date', $filtres['date']));
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Récupère les offres proposées par l'intervenant connecté.
     */
    public function getMesOffres(User $intervenant, int $perPage = 10): LengthAwarePaginator
    {
        return Offre::where('etudiant_id', $intervenant->id)
            ->with(['categorie:id,nom', 'creneaux'])
            ->withCount('creneaux')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Consulte le détail d'une offre (avec contrôle de visibilité RG04/RG19).
     *
     *
     * @throws OffreNonAutoriseeException
     */
    public function getOffre(int $id, ?User $visiteur = null): Offre
    {
        $offre = Offre::with([
            'categorie',
            'intervenant:id,nom,prenom,filiere',
            'creneaux' => function ($q) {
                $q->futurs()->disponibles()->orderBy('date')->orderBy('heure_debut');
            },
        ])->findOrFail($id);

        // Si l'offre n'est pas publiée, seuls le propriétaire et l'administrateur peuvent la consulter
        if ($offre->statut !== 'publiee') {
            $autorise = $visiteur && (
                $visiteur->isAdministrateur() ||
                (int) $offre->etudiant_id === (int) $visiteur->id
            );

            if (! $autorise) {
                throw new OffreNonAutoriseeException("Cette offre n'est plus accessible au public.");
            }
        }

        return $offre;
    }
}
