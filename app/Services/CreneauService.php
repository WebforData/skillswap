<?php

namespace App\Services;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\ConflitHoraireException;
use App\Exceptions\CreneauInvalideException;
use App\Exceptions\CreneauOccupeException;
use App\Exceptions\DureeCreneauInvalideException;
use App\Exceptions\OffreNonAutoriseeException;
use App\Exceptions\OffreStatutInvalideException;
use App\Models\Creneau;
use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\Offre;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreneauService
{
    /**
     * Ajoute un créneau de disponibilité pour une offre publiée (RG02, RG04, RG08).
     *
     * @param  User   $intervenant
     * @param  int    $offreId
     * @param  array  $data
     * @return Creneau
     *
     * @throws CompteDesactiveException
     * @throws OffreNonAutoriseeException
     * @throws OffreStatutInvalideException
     * @throws CreneauInvalideException
     * @throws DureeCreneauInvalideException
     * @throws ConflitHoraireException
     */
    public function ajouterCreneau(User $intervenant, int $offreId, array $data): Creneau
    {
        // 1. RG18 : Compte actif requis
        if (!$intervenant->isActif()) {
            throw new CompteDesactiveException("Votre compte est désactivé (RG18). Impossible d'ajouter un créneau.");
        }

        return DB::transaction(function () use ($intervenant, $offreId, $data) {
            // 2. Verrouiller et vérifier l'offre
            $offre = Offre::where('id', $offreId)->lockForUpdate()->firstOrFail();

            // 3. Propriété de l'offre
            if ((int) $offre->etudiant_id !== (int) $intervenant->id && !$intervenant->isAdministrateur()) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à ajouter des créneaux pour cette offre.");
            }

            // 4. RG04 : L'offre doit être publiée
            if ($offre->statut !== 'publiee') {
                throw new OffreStatutInvalideException("Seule une offre publiée peut recevoir de nouveaux créneaux (RG04).");
            }

            // 5. Calcul des dates et vérification du futur (RG04)
            $date = Carbon::parse($data['date'])->toDateString();
            $heureDebut = Carbon::parse($data['heure_debut'])->format('H:i:s');
            $heureFin = Carbon::parse($data['heure_fin'])->format('H:i:s');

            $debut = Carbon::parse("{$date} {$heureDebut}");
            $fin = Carbon::parse("{$date} {$heureFin}");
            if ($heureFin < $heureDebut) {
                $fin->addDay();
            }

            if ($debut->lessThanOrEqualTo(now())) {
                throw new CreneauInvalideException("La date et l'heure de début du créneau doivent être strictement futures (RG04).");
            }

            // 6. RG02 : Concordance stricte durée créneau / offre
            $dureeMinutes = (int) $debut->diffInMinutes($fin, false);
            $dureeOffreMinutes = (int) $offre->duree * 60;

            if ($dureeMinutes !== $dureeOffreMinutes) {
                throw new DureeCreneauInvalideException("La durée du créneau ({$dureeMinutes} min) ne correspond pas à la durée spécifiée dans l'offre ({$dureeOffreMinutes} min) (RG02).");
            }

            // 7. RG08 : Vérification des chevauchements d'horaires pour l'intervenant
            $this->verifierChevauchementIntervenant($intervenant->id, $date, $heureDebut, $heureFin);

            // 8. Création du créneau
            $creneau = new Creneau();
            $creneau->forceFill([
                'offre_id' => $offre->id,
                'date' => $date,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'lieu_public' => trim($data['lieu_public']),
                'lien_ou_lieu_prive' => trim($data['lien_ou_lieu_prive']),
            ]);
            $creneau->save();

            return $creneau->fresh(['offre']);
        });
    }

    /**
     * Modifie un créneau horaire existant (RG02, RG07, RG08, RG17).
     *
     * @param  User   $intervenant
     * @param  int    $creneauId
     * @param  array  $data
     * @return Creneau
     *
     * @throws CompteDesactiveException
     * @throws OffreNonAutoriseeException
     * @throws CreneauOccupeException
     * @throws CreneauInvalideException
     * @throws DureeCreneauInvalideException
     * @throws ConflitHoraireException
     */
    public function modifierCreneau(User $intervenant, int $creneauId, array $data): Creneau
    {
        if (!$intervenant->isActif()) {
            throw new CompteDesactiveException("Votre compte est désactivé (RG18).");
        }

        return DB::transaction(function () use ($intervenant, $creneauId, $data) {
            $creneau = Creneau::with('offre')->where('id', $creneauId)->lockForUpdate()->firstOrFail();

            // 1. Autorisation
            if ((int) $creneau->offre->etudiant_id !== (int) $intervenant->id && !$intervenant->isAdministrateur()) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à modifier ce créneau.");
            }

            // 2. RG07 / RG09 : Interdiction formelle de modifier un créneau attribué
            $estOccupe = Reservation::where('creneau_id', $creneau->id)
                ->whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
                ->lockForUpdate()
                ->exists();

            if ($estOccupe) {
                throw new CreneauOccupeException("Impossible de modifier un créneau déjà attribué à une réservation confirmée ou en cours (RG07).");
            }

            // 3. Calcul des nouvelles valeurs
            $nouvelleDate = isset($data['date']) ? Carbon::parse($data['date'])->toDateString() : (is_string($creneau->date) ? $creneau->date : $creneau->date->format('Y-m-d'));
            $nouveauDebut = isset($data['heure_debut']) ? Carbon::parse($data['heure_debut'])->format('H:i:s') : $creneau->heure_debut;
            $nouveauFin = isset($data['heure_fin']) ? Carbon::parse($data['heure_fin'])->format('H:i:s') : $creneau->heure_fin;

            $debut = Carbon::parse("{$nouvelleDate} {$nouveauDebut}");
            $fin = Carbon::parse("{$nouvelleDate} {$nouveauFin}");
            if ($nouveauFin < $nouveauDebut) {
                $fin->addDay();
            }

            // 4. Vérification date future (RG04)
            if ($debut->lessThanOrEqualTo(now())) {
                throw new CreneauInvalideException("La nouvelle date et heure doivent être strictement futures (RG04).");
            }

            // 5. RG02 : Concordance durée
            $dureeMinutes = (int) $debut->diffInMinutes($fin, false);
            $dureeOffreMinutes = (int) $creneau->offre->duree * 60;
            if ($dureeMinutes !== $dureeOffreMinutes) {
                throw new DureeCreneauInvalideException("La durée modifiée ({$dureeMinutes} min) ne correspond pas à la durée de l'offre ({$dureeOffreMinutes} min) (RG02).");
            }

            // 6. RG08 : Chevauchements en excluant ce créneau
            $this->verifierChevauchementIntervenant(
                $creneau->offre->etudiant_id,
                $nouvelleDate,
                $nouveauDebut,
                $nouveauFin,
                $creneau->id
            );

            // 7. RG17 : Annulation automatique des demandes en attente sur ce créneau (puisque les horaires ont changé)
            $demandesEnAttente = Reservation::where('creneau_id', $creneau->id)
                ->where('statut', 'en_attente')
                ->lockForUpdate()
                ->get();

            foreach ($demandesEnAttente as $demande) {
                $demande->statut = 'annulee';
                $demande->motif_refus = "Réservation annulée suite à la modification des horaires du créneau par l'intervenant.";
                $demande->save();
            }

            // 8. Mise à jour des informations
            $creneau->date = $nouvelleDate;
            $creneau->heure_debut = $nouveauDebut;
            $creneau->heure_fin = $nouveauFin;
            if (isset($data['lieu_public'])) {
                $creneau->lieu_public = trim($data['lieu_public']);
            }
            if (isset($data['lien_ou_lieu_prive'])) {
                $creneau->lien_ou_lieu_prive = trim($data['lien_ou_lieu_prive']);
            }
            $creneau->save();

            return $creneau->fresh(['offre']);
        });
    }

    /**
     * Supprime un créneau horaire non attribué (RG07, RG17).
     *
     * @param  User   $intervenant
     * @param  int    $creneauId
     * @return bool
     *
     * @throws CompteDesactiveException
     * @throws OffreNonAutoriseeException
     * @throws CreneauOccupeException
     */
    public function supprimerCreneau(User $intervenant, int $creneauId): bool
    {
        if (!$intervenant->isActif()) {
            throw new CompteDesactiveException("Votre compte est désactivé (RG18).");
        }

        return DB::transaction(function () use ($intervenant, $creneauId) {
            $creneau = Creneau::with('offre')->where('id', $creneauId)->lockForUpdate()->firstOrFail();

            // 1. Autorisation
            if ((int) $creneau->offre->etudiant_id !== (int) $intervenant->id && !$intervenant->isAdministrateur()) {
                throw new OffreNonAutoriseeException("Vous n'êtes pas autorisé à supprimer ce créneau.");
            }

            // 2. RG07 : Rejet strict si une réservation est confirmée, réalisée ou en litige
            $estOccupe = Reservation::where('creneau_id', $creneau->id)
                ->whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
                ->lockForUpdate()
                ->exists();

            if ($estOccupe) {
                throw new CreneauOccupeException("Impossible de supprimer un créneau ayant une réservation confirmée, réalisée ou en litige (RG07, RG17).");
            }

            // 3. RG17 : Annulation automatique des demandes en attente
            $demandesEnAttente = Reservation::where('creneau_id', $creneau->id)
                ->where('statut', 'en_attente')
                ->lockForUpdate()
                ->get();

            foreach ($demandesEnAttente as $demande) {
                $demande->statut = 'annulee';
                $demande->motif_refus = "Réservation annulée suite à la suppression du créneau par l'intervenant.";
                $demande->save();
            }

            // Pour respecter la contrainte d'intégrité BDD restrictOnDelete sur creneau_id,
            // les réservations non attribuées (en attente, refusées ou annulées) de ce créneau sont purgées avant suppression.
            Reservation::where('creneau_id', $creneau->id)->delete();

            // 4. Suppression du créneau
            return (bool) $creneau->delete();
        });
    }

    /**
     * Vérifie les chevauchements d'horaires pour un intervenant (RG08).
     *
     * Formule stricte : debut_existant < fin_nouveau ET fin_existant > debut_nouveau
     * Deux séances consécutives sont permises.
     *
     * @throws ConflitHoraireException
     */
    private function verifierChevauchementIntervenant(
        int $userId,
        string $date,
        string $heureDebut,
        string $heureFin,
        ?int $exclureCreneauId = null
    ): void {
        $nouveauDebut = Carbon::parse("{$date} {$heureDebut}");
        $nouveauFin = Carbon::parse("{$date} {$heureFin}");
        if ($heureFin < $heureDebut) {
            $nouveauFin->addDay();
        }

        // 1. Chevauchement avec d'autres créneaux proposés par cet intervenant
        $autresCreneaux = Creneau::whereHas('offre', fn($q) => $q->where('etudiant_id', $userId))
            ->when($exclureCreneauId, fn($q) => $q->where('id', '!=', $exclureCreneauId))
            ->get();

        foreach ($autresCreneaux as $autre) {
            $autreDateStr = is_string($autre->date) ? $autre->date : $autre->date->format('Y-m-d');
            $autreDebut = Carbon::parse("{$autreDateStr} {$autre->heure_debut}");
            $autreFin = Carbon::parse("{$autreDateStr} {$autre->heure_fin}");
            if ($autre->heure_fin < $autre->heure_debut) {
                $autreFin->addDay();
            }

            // Intersection stricte
            if ($autreDebut->lessThan($nouveauFin) && $autreFin->greaterThan($nouveauDebut)) {
                throw new ConflitHoraireException("Conflit de planning : vous avez déjà proposé un autre créneau sur cet intervalle ({$autreDebut->format('d/m/Y H:i')} - {$autreFin->format('H:i')}) (RG08).");
            }
        }

        // 2. Chevauchement avec des réservations confirmées/engagées de l'intervenant (en tant que formateur OU bénéficiaire)
        $reservationsEngagees = Reservation::whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
            ->where(function ($query) use ($userId) {
                $query->where('beneficiaire_id', $userId)
                    ->orWhereHas('creneau.offre', fn($q) => $q->where('etudiant_id', $userId));
            })
            ->with(['creneau.offre'])
            ->get();

        foreach ($reservationsEngagees as $res) {
            $c = $res->creneau;
            if (!$c) {
                continue;
            }
            if ($exclureCreneauId && (int) $c->id === (int) $exclureCreneauId) {
                continue;
            }

            $cDateStr = is_string($c->date) ? $c->date : $c->date->format('Y-m-d');
            $cDebut = Carbon::parse("{$cDateStr} {$c->heure_debut}");
            $cFin = Carbon::parse("{$cDateStr} {$c->heure_fin}");
            if ($c->heure_fin < $c->heure_debut) {
                $cFin->addDay();
            }

            if ($cDebut->lessThan($nouveauFin) && $cFin->greaterThan($nouveauDebut)) {
                throw new ConflitHoraireException("Conflit de planning : vous participez déjà à une séance confirmée sur cet intervalle ({$cDebut->format('d/m/Y H:i')} - {$cFin->format('H:i')}) (RG08).");
            }
        }
    }

    /**
     * Récupère le planning consolidé d'un utilisateur (RG08, RG19).
     *
     * @param  User   $utilisateur
     * @param  array  $filtres  ['a_venir', 'historique', 'statut', 'date_debut', 'date_fin', 'role']
     * @return array
     */
    public function getPlanning(User $utilisateur, array $filtres = []): array
    {
        $roleFiltre = $filtres['role'] ?? 'tous';

        $query = Reservation::with([
            'creneau.offre.intervenant',
            'creneau.offre.categorie',
            'beneficiaire',
            'litige',
        ]);

        // Filtrage par rôle de l'utilisateur
        if ($roleFiltre === 'intervenant') {
            $query->whereHas('creneau.offre', fn($q) => $q->where('etudiant_id', $utilisateur->id));
        } elseif ($roleFiltre === 'beneficiaire') {
            $query->where('beneficiaire_id', $utilisateur->id);
        } else {
            $query->where(function ($q) use ($utilisateur) {
                $q->where('beneficiaire_id', $utilisateur->id)
                    ->orWhereHas('creneau.offre', fn($sub) => $sub->where('etudiant_id', $utilisateur->id));
            });
        }

        // Filtre par statut spécifique
        if (!empty($filtres['statut'])) {
            $query->where('statut', $filtres['statut']);
        }

        // Filtre par plage de dates
        if (!empty($filtres['date_debut'])) {
            $query->whereHas('creneau', fn($q) => $q->where('date', '>=', $filtres['date_debut']));
        }
        if (!empty($filtres['date_fin'])) {
            $query->whereHas('creneau', fn($q) => $q->where('date', '<=', $filtres['date_fin']));
        }

        $reservations = $query->get();

        // Récupérer les évaluations rédigées par cet utilisateur pour déterminer peut_evaluer
        $evaluationsRedigees = Evaluation::where('auteur_id', $utilisateur->id)
            ->pluck('reservation_id')
            ->flip()
            ->all();

        $maintenant = now();

        $planning = [];

        foreach ($reservations as $res) {
            $creneau = $res->creneau;
            if (!$creneau) {
                continue;
            }

            $dateStr = is_string($creneau->date) ? $creneau->date : $creneau->date->format('Y-m-d');
            $debutSeance = Carbon::parse("{$dateStr} {$creneau->heure_debut}");
            $finSeance = Carbon::parse("{$dateStr} {$creneau->heure_fin}");
            if ($creneau->heure_fin < $creneau->heure_debut) {
                $finSeance->addDay();
            }

            $estPasse = $maintenant->greaterThanOrEqualTo($finSeance);
            $estCommence = $maintenant->greaterThanOrEqualTo($debutSeance);

            // Filtres temporels avancés
            $aVenir = (!empty($filtres['a_venir']) && filter_var($filtres['a_venir'], FILTER_VALIDATE_BOOLEAN)) || (($filtres['filtre'] ?? '') === 'a_venir');
            $historique = (!empty($filtres['historique']) && filter_var($filtres['historique'], FILTER_VALIDATE_BOOLEAN)) || (($filtres['filtre'] ?? '') === 'historique');

            if ($aVenir) {
                if ($estPasse || in_array($res->statut, ['realisee', 'annulee', 'refusee'])) {
                    continue;
                }
            }

            if ($historique) {
                if (!$estPasse && !in_array($res->statut, ['realisee', 'annulee', 'refusee'])) {
                    continue;
                }
            }

            $isIntervenant = ((int) $creneau->offre?->etudiant_id === (int) $utilisateur->id);
            $autreParticipant = $isIntervenant ? $res->beneficiaire : $creneau->offre?->intervenant;

            // RG19 : Coordonnées privées visibles uniquement si autorisé
            $coordonneesPrivees = $creneau->peutAccederAuxDetailsPrives($utilisateur)
                ? $creneau->lien_ou_lieu_prive
                : null;

            // Calcul des indicateurs d'actions possibles
            $peutAnnuler = false;
            if ($res->statut === 'en_attente' && !$isIntervenant) {
                $peutAnnuler = true; // Retrait bénéficiaire
            } elseif ($res->statut === 'acceptee' && $maintenant->lessThan($debutSeance)) {
                $peutAnnuler = true; // Annulation pré-séance (RG10)
            }

            $peutConfirmer = false;
            if ($res->statut === 'acceptee' && $estPasse && !$res->litige) {
                if ($isIntervenant && !$res->confirmation_intervenant) {
                    $peutConfirmer = true;
                } elseif (!$isIntervenant && !$res->confirmation_beneficiaire) {
                    $peutConfirmer = true;
                }
            }

            $peutOuvrirLitige = false;
            if ($res->statut === 'acceptee' && $estCommence && !$res->litige) {
                $peutOuvrirLitige = true;
            }

            $peutEvaluer = false;
            if ($res->statut === 'realisee' && !isset($evaluationsRedigees[$res->id])) {
                $peutEvaluer = true;
            }

            $planning[] = [
                'reservation_id' => $res->id,
                'statut' => $res->statut,
                'cout_credits' => $res->cout_credits,
                'role' => $isIntervenant ? 'intervenant' : 'beneficiaire',
                'date_demande' => $res->date_demande?->toIso8601String(),
                'motif_refus' => $res->motif_refus,
                'autre_participant' => [
                    'id' => $autreParticipant?->id,
                    'nom' => $autreParticipant?->nom,
                    'prenom' => $autreParticipant?->prenom,
                    'filiere' => $autreParticipant?->filiere,
                ],
                'offre' => [
                    'id' => $creneau->offre?->id,
                    'titre' => $creneau->offre?->titre,
                    'competence' => $creneau->offre?->competence,
                    'niveau' => $creneau->offre?->niveau,
                    'mode' => $creneau->offre?->mode,
                    'duree' => $creneau->offre?->duree,
                    'categorie' => $creneau->offre?->categorie?->nom,
                ],
                'creneau' => [
                    'id' => $creneau->id,
                    'date' => $dateStr,
                    'heure_debut' => $creneau->heure_debut,
                    'heure_fin' => $creneau->heure_fin,
                    'lieu_public' => $creneau->lieu_public,
                    'lieu_ou_lien_prive' => $coordonneesPrivees, // RG19
                ],
                'actions_possibles' => [
                    'peut_annuler' => $peutAnnuler,
                    'peut_confirmer' => $peutConfirmer,
                    'peut_ouvrir_litige' => $peutOuvrirLitige,
                    'peut_evaluer' => $peutEvaluer,
                ],
            ];
        }

        // Trier chronologiquement par date et heure de début
        usort($planning, function ($a, $b) {
            $tA = "{$a['creneau']['date']} {$a['creneau']['heure_debut']}";
            $tB = "{$b['creneau']['date']} {$b['creneau']['heure_debut']}";
            return strcmp($tA, $tB);
        });

        return $planning;
    }
}
