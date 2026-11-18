<?php

namespace App\Services;

use App\Exceptions\AccesReservationNonAutoriseException;
use App\Exceptions\AutoReservationException;
use App\Exceptions\CompteDesactiveException;
use App\Exceptions\ConfirmationSeanceException;
use App\Exceptions\ConflitHoraireException;
use App\Exceptions\CreneauIndisponibleException;
use App\Exceptions\DelaiAnnulationDepasseException;
use App\Exceptions\OffreInvalideException;
use App\Exceptions\SoldeInsuffisantPourReservationException;
use App\Exceptions\StatutReservationInvalideException;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * Crée une nouvelle demande de réservation en attente (RG02 à RG05, RG08, RG18).
     *
     * @param  User  $beneficiaire
     * @param  int   $creneauId
     * @return Reservation
     *
     * @throws CompteDesactiveException
     * @throws CreneauIndisponibleException
     * @throws OffreInvalideException
     * @throws AutoReservationException
     * @throws ConflitHoraireException
     * @throws SoldeInsuffisantPourReservationException
     */
    public function demanderReservation(User $beneficiaire, int $creneauId): Reservation
    {
        // RG18 : Empêcher les comptes désactivés d'effectuer des opérations
        $this->verifierCompteActif($beneficiaire);

        return DB::transaction(function () use ($beneficiaire, $creneauId) {
            // 1. Verrouiller le créneau (Ordre 1) avec lockForUpdate()
            $creneau = Creneau::where('id', $creneauId)
                ->lockForUpdate()
                ->firstOrFail();

            $creneau->load('offre.intervenant');
            $offre = $creneau->offre;

            if (!$offre) {
                throw new OffreInvalideException("L'offre associée au créneau est introuvable.");
            }

            // RG04 : Vérifier que l'offre est publiée
            if ($offre->statut !== 'publiee') {
                throw new OffreInvalideException("Cette offre n'est actuellement pas disponible à la réservation (RG04).");
            }

            // RG18 : Vérifier que le compte de l'intervenant est actif
            if (!$offre->intervenant || !$offre->intervenant->isActif()) {
                throw new OffreInvalideException("Le compte de l'intervenant est désactivé (RG18).");
            }

            // RG04 : Vérifier que le créneau est strictement situé dans le futur
            $dateStr = is_string($creneau->date) ? $creneau->date : $creneau->date->format('Y-m-d');
            $debutSeance = Carbon::parse("{$dateStr} {$creneau->heure_debut}");
            if (now()->greaterThanOrEqualTo($debutSeance)) {
                throw new CreneauIndisponibleException("Ce créneau n'est plus disponible ou est déjà passé (RG04).");
            }

            // RG04 & RG07 : Vérifier que le créneau n'est pas déjà attribué
            $dejaAttribue = Reservation::where('creneau_id', $creneau->id)
                ->whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
                ->exists();

            if ($dejaAttribue) {
                throw new CreneauIndisponibleException("Ce créneau a déjà été attribué et n'est plus disponible (RG04, RG07).");
            }

            // RG03 : Interdiction formelle de réserver sa propre offre
            if ((int) $offre->etudiant_id === (int) $beneficiaire->id) {
                throw new AutoReservationException("Un étudiant ne peut pas réserver sa propre offre (RG03).");
            }

            // RG03 : Empêcher une double demande active sur le même créneau
            $demandeActive = Reservation::where('creneau_id', $creneau->id)
                ->where('beneficiaire_id', $beneficiaire->id)
                ->whereIn('statut', ['en_attente', 'acceptee'])
                ->exists();

            if ($demandeActive) {
                throw new AutoReservationException("Vous avez déjà une demande active pour ce créneau (RG03).");
            }

            // RG08 : Contrôle préventif des conflits d'horaires pour le bénéficiaire
            $this->verifierConflitHoraire($beneficiaire->id, $creneau);

            // RG02 & RG05 : Vérification du solde disponible (AUCUN blocage à ce stade)
            $coutCredits = (int) $offre->duree;
            if (!$this->creditService->verifierSoldeDisponible($beneficiaire, $coutCredits)) {
                throw new SoldeInsuffisantPourReservationException("Votre solde disponible est insuffisant pour demander cette séance de {$coutCredits} crédit(s) (RG05).");
            }

            // Création de la réservation au statut 'en_attente'
            $reservation = new Reservation();
            $reservation->forceFill([
                'creneau_id' => $creneau->id,
                'beneficiaire_id' => $beneficiaire->id,
                'date_demande' => now(),
                'statut' => 'en_attente',
                'cout_credits' => $coutCredits,
                'motif_refus' => null,
                'confirmation_intervenant' => false,
                'date_confirmation_intervenant' => null,
                'confirmation_beneficiaire' => false,
                'date_confirmation_beneficiaire' => null,
            ]);
            $reservation->save();

            return $reservation->fresh(['creneau.offre.intervenant', 'beneficiaire']);
        }, 3);
    }

    /**
     * Accepte une demande de réservation, bloque les crédits et rejette les demandes concurrentes (RG06, RG07, RG08).
     *
     * @param  User  $intervenant
     * @param  int   $reservationId
     * @return Reservation
     *
     * @throws CompteDesactiveException
     * @throws AccesReservationNonAutoriseException
     * @throws StatutReservationInvalideException
     * @throws CreneauIndisponibleException
     * @throws ConflitHoraireException
     */
    public function accepterReservation(User $intervenant, int $reservationId): Reservation
    {
        $this->verifierCompteActif($intervenant);

        return DB::transaction(function () use ($intervenant, $reservationId) {
            // Identifier le créneau pour verrouiller Creneau en PREMIER (Ordre canonique 1)
            $preload = Reservation::findOrFail($reservationId);
            $lockedCreneau = Creneau::where('id', $preload->creneau_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verrouiller la réservation en SECOND (Ordre canonique 2)
            $lockedReservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReservation->load('creneau.offre.intervenant', 'beneficiaire');

            // Vérifier que l'utilisateur connecté est bien l'intervenant légitime
            if (!$lockedReservation->isIntervenant($intervenant)) {
                throw new AccesReservationNonAutoriseException("Seul l'intervenant de l'offre peut accepter cette demande.");
            }

            // Vérifier que la réservation est bien au statut 'en_attente'
            if ($lockedReservation->statut !== 'en_attente') {
                throw new StatutReservationInvalideException("Seule une réservation au statut 'en_attente' peut être acceptée (statut actuel : '{$lockedReservation->statut}').");
            }

            // RG07 : Empêcher les doubles acceptations sous verrou
            $creneauDejaAttribue = Reservation::where('creneau_id', $lockedCreneau->id)
                ->where('id', '!=', $lockedReservation->id)
                ->whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
                ->exists();

            if ($creneauDejaAttribue) {
                throw new CreneauIndisponibleException("Ce créneau est déjà attribué à une autre réservation (RG07).");
            }

            // RG04 : Vérifier que la séance n'est pas déjà commencée
            $dateStr = is_string($lockedCreneau->date) ? $lockedCreneau->date : $lockedCreneau->date->format('Y-m-d');
            $debutSeance = Carbon::parse("{$dateStr} {$lockedCreneau->heure_debut}");
            if (now()->greaterThanOrEqualTo($debutSeance)) {
                throw new CreneauIndisponibleException("Impossible d'accepter une réservation pour un créneau déjà débuté ou passé (RG04).");
            }

            // RG08 : Contrôle des conflits d'horaires pour les DEUX participants
            $this->verifierConflitHoraire($intervenant->id, $lockedCreneau, $lockedReservation->id);
            $this->verifierConflitHoraire($lockedReservation->beneficiaire_id, $lockedCreneau, $lockedReservation->id);

            // RG06 : Séquestre financier des crédits auprès de CreditService
            $this->creditService->bloquerCredits($lockedReservation);

            // Mettre à jour le statut de la réservation retenue
            $lockedReservation->statut = 'acceptee';
            $lockedReservation->save();

            // RG07 : Rejet automatique de toutes les autres demandes en attente sur ce créneau
            Reservation::where('creneau_id', $lockedCreneau->id)
                ->where('id', '!=', $lockedReservation->id)
                ->where('statut', 'en_attente')
                ->update([
                    'statut' => 'refusee',
                    'motif_refus' => 'Créneau déjà attribué (RG07)',
                    'updated_at' => now(),
                ]);

            return $lockedReservation->fresh(['creneau.offre.intervenant', 'beneficiaire']);
        }, 3);
    }

    /**
     * Refuse une demande en attente avec un motif explicite (RG09).
     *
     * @param  User    $intervenant
     * @param  int     $reservationId
     * @param  string? $motif
     * @return Reservation
     *
     * @throws CompteDesactiveException
     * @throws AccesReservationNonAutoriseException
     * @throws StatutReservationInvalideException
     */
    public function refuserReservation(User $intervenant, int $reservationId, ?string $motif = null): Reservation
    {
        $this->verifierCompteActif($intervenant);

        return DB::transaction(function () use ($intervenant, $reservationId, $motif) {
            $lockedReservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedReservation->isIntervenant($intervenant)) {
                throw new AccesReservationNonAutoriseException("Seul l'intervenant de l'offre peut refuser cette demande.");
            }

            if ($lockedReservation->statut !== 'en_attente') {
                throw new StatutReservationInvalideException("Seule une réservation au statut 'en_attente' peut être refusée (statut actuel : '{$lockedReservation->statut}').");
            }

            $lockedReservation->statut = 'refusee';
            $lockedReservation->motif_refus = $motif ?? "Demande refusée par l'intervenant (RG09).";
            $lockedReservation->save();

            return $lockedReservation->fresh(['creneau.offre.intervenant', 'beneficiaire']);
        }, 3);
    }

    /**
     * Permet au demandeur de retirer sa réservation tant qu'elle est en attente (RG09).
     *
     * @param  User  $beneficiaire
     * @param  int   $reservationId
     * @return Reservation
     *
     * @throws CompteDesactiveException
     * @throws AccesReservationNonAutoriseException
     * @throws StatutReservationInvalideException
     */
    public function retirerDemandeEnAttente(User $beneficiaire, int $reservationId): Reservation
    {
        $this->verifierCompteActif($beneficiaire);

        return DB::transaction(function () use ($beneficiaire, $reservationId) {
            $lockedReservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedReservation->isBeneficiaire($beneficiaire)) {
                throw new AccesReservationNonAutoriseException("Seul le bénéficiaire peut retirer sa propre demande en attente.");
            }

            if ($lockedReservation->statut !== 'en_attente') {
                throw new StatutReservationInvalideException("Seule une demande au statut 'en_attente' peut être retirée (statut actuel : '{$lockedReservation->statut}').");
            }

            $lockedReservation->statut = 'annulee';
            $lockedReservation->motif_refus = "Demande retirée par le bénéficiaire (RG09).";
            $lockedReservation->save();

            return $lockedReservation->fresh(['creneau.offre.intervenant', 'beneficiaire']);
        }, 3);
    }

    /**
     * Annule une réservation acceptée avant le début de la séance et libère les crédits (RG10).
     *
     * @param  User    $participant
     * @param  int     $reservationId
     * @param  string? $motif
     * @return Reservation
     *
     * @throws CompteDesactiveException
     * @throws AccesReservationNonAutoriseException
     * @throws StatutReservationInvalideException
     * @throws DelaiAnnulationDepasseException
     */
    public function annulerReservationAcceptee(User $participant, int $reservationId, ?string $motif = null): Reservation
    {
        $this->verifierCompteActif($participant);

        return DB::transaction(function () use ($participant, $reservationId, $motif) {
            // Verrouiller Creneau en PREMIER (Ordre canonique 1)
            $preload = Reservation::findOrFail($reservationId);
            $lockedCreneau = Creneau::where('id', $preload->creneau_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verrouiller Reservation en SECOND (Ordre canonique 2)
            $lockedReservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReservation->load('creneau.offre.intervenant', 'beneficiaire');

            // Vérifier que l'utilisateur est soit le bénéficiaire, soit l'intervenant, soit un admin
            if (!$lockedReservation->isParticipant($participant) && !$participant->isAdministrateur()) {
                throw new AccesReservationNonAutoriseException("Seul un participant à la séance ou un administrateur peut l'annuler.");
            }

            // Statut obligatoire : 'acceptee'
            if ($lockedReservation->statut !== 'acceptee') {
                throw new StatutReservationInvalideException("Seule une réservation acceptée peut être annulée avant séance (statut actuel : '{$lockedReservation->statut}').");
            }

            // RG10 : Refuser strictement toute annulation directe après le début de la séance
            $dateStr = is_string($lockedCreneau->date) ? $lockedCreneau->date : $lockedCreneau->date->format('Y-m-d');
            $debutSeance = Carbon::parse("{$dateStr} {$lockedCreneau->heure_debut}");
            if (now()->greaterThanOrEqualTo($debutSeance)) {
                throw new DelaiAnnulationDepasseException("L'annulation directe et la libération des crédits sont impossibles après le début de la séance. Un litige doit être ouvert (RG10).");
            }

            // RG10 : Libération des crédits bloqués au bénéficiaire
            $this->creditService->libererCredits($lockedReservation);

            // Mettre à jour la réservation
            $lockedReservation->statut = 'annulee';
            $lockedReservation->motif_refus = $motif ?? "Réservation annulée avant séance par {$participant->prenom} {$participant->nom} (RG10).";
            $lockedReservation->save();

            return $lockedReservation->fresh(['creneau.offre.intervenant', 'beneficiaire']);
        }, 3);
    }

    /**
     * Enregistre la confirmation d'un participant post-séance et déclenche le transfert lors de la 2ème confirmation (RG11, RG12, RG14).
     *
     * @param  User  $participant
     * @param  int   $reservationId
     * @return Reservation
     *
     * @throws CompteDesactiveException
     * @throws AccesReservationNonAutoriseException
     * @throws StatutReservationInvalideException
     * @throws ConfirmationSeanceException
     */
    public function confirmerRealisation(User $participant, int $reservationId): Reservation
    {
        $this->verifierCompteActif($participant);

        return DB::transaction(function () use ($participant, $reservationId) {
            // Verrouiller Creneau en PREMIER (Ordre canonique 1)
            $preload = Reservation::findOrFail($reservationId);
            $lockedCreneau = Creneau::where('id', $preload->creneau_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verrouiller Reservation en SECOND (Ordre canonique 2)
            $lockedReservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReservation->load('creneau.offre.intervenant', 'beneficiaire');

            // Vérifier la légitimité du participant
            if (!$lockedReservation->isParticipant($participant)) {
                throw new AccesReservationNonAutoriseException("Seuls les participants à la séance peuvent confirmer sa réalisation (RG11).");
            }

            // Seule une réservation acceptée peut recevoir une confirmation
            if ($lockedReservation->statut !== 'acceptee') {
                throw new StatutReservationInvalideException("Seule une réservation au statut 'acceptee' peut être confirmée (statut actuel : '{$lockedReservation->statut}').");
            }

            // RG11 : Confirmation autorisée uniquement APRÈS l'heure de fin prévue
            $dateStr = is_string($lockedCreneau->date) ? $lockedCreneau->date : $lockedCreneau->date->format('Y-m-d');
            $finSeance = Carbon::parse("{$dateStr} {$lockedCreneau->heure_fin}");
            if ($lockedCreneau->heure_fin < $lockedCreneau->heure_debut) {
                $finSeance->addDay();
            }
            if (now()->lessThan($finSeance)) {
                throw new ConfirmationSeanceException("La confirmation de la séance n'est autorisée qu'après son heure de fin prévue ({$finSeance->format('d/m/Y H:i')}) (RG11).");
            }

            // RG12 : Vérifier qu'aucun litige n'est actuellement ouvert
            $litigeOuvert = Litige::where('reservation_id', $lockedReservation->id)
                ->where('statut', 'ouvert')
                ->exists();

            if ($litigeOuvert) {
                throw new ConfirmationSeanceException("Confirmation impossible : un litige est actuellement ouvert pour cette réservation (RG12).");
            }

            // Enregistrer la confirmation respective (une seule fois de manière idempotente)
            if ($lockedReservation->isBeneficiaire($participant)) {
                if (!$lockedReservation->confirmation_beneficiaire) {
                    $lockedReservation->confirmation_beneficiaire = true;
                    $lockedReservation->date_confirmation_beneficiaire = now();
                }
            } elseif ($lockedReservation->isIntervenant($participant)) {
                if (!$lockedReservation->confirmation_intervenant) {
                    $lockedReservation->confirmation_intervenant = true;
                    $lockedReservation->date_confirmation_intervenant = now();
                }
            }

            $lockedReservation->save();

            // Recharger les données fraîches depuis la base
            $lockedReservation->refresh();

            // RG11 : Si les DEUX confirmations sont désormais positives et que le statut est encore 'acceptee'
            if ($lockedReservation->isDoublementConfirmee() && $lockedReservation->statut === 'acceptee') {
                // Respect du trigger MySQL trg_check_reservation_realisee_before_update :
                // exige confirmation_intervenant = TRUE et confirmation_beneficiaire = TRUE lors du passage à 'realisee'.
                $lockedReservation->statut = 'realisee';
                $lockedReservation->save();

                // Transfert financier atomique des crédits (débit bénéficiaire / crédit intervenant)
                // Si le transfert échoue, l'intégralité de la transaction est annulée par rollback
                $this->creditService->transfererCredits($lockedReservation);
            }

            return $lockedReservation->fresh(['creneau.offre.intervenant', 'beneficiaire']);
        }, 3);
    }

    /**
     * Ouvre un litige sur une réservation acceptée après le début de séance (RG12, RG13, RG14).
     * Les crédits demeurent sous séquestre jusqu'à décision arbitrale de l'administrateur.
     *
     * @param  User    $declarant
     * @param  int     $reservationId
     * @param  string  $description
     * @return Litige
     *
     * @throws CompteDesactiveException
     * @throws AccesReservationNonAutoriseException
     * @throws StatutReservationInvalideException
     */
    public function ouvrirLitige(User $declarant, int $reservationId, string $description): Litige
    {
        $this->verifierCompteActif($declarant);

        return DB::transaction(function () use ($declarant, $reservationId, $description) {
            // Verrouiller Creneau en PREMIER (Ordre canonique 1)
            $preload = Reservation::findOrFail($reservationId);
            $lockedCreneau = Creneau::where('id', $preload->creneau_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verrouiller Reservation en SECOND (Ordre canonique 2)
            $lockedReservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedReservation->isParticipant($declarant)) {
                throw new AccesReservationNonAutoriseException("Seul un participant légitime à la séance peut ouvrir un litige.");
            }

            // Statut de la réservation : doit être 'acceptee'
            if ($lockedReservation->statut !== 'acceptee') {
                throw new StatutReservationInvalideException("Un litige ne peut être ouvert que pour une réservation acceptée (statut actuel : '{$lockedReservation->statut}').");
            }

            // Un litige ne peut être ouvert qu'après le début de la séance (avant, c'est une annulation RG10)
            $dateStr = is_string($lockedCreneau->date) ? $lockedCreneau->date : $lockedCreneau->date->format('Y-m-d');
            $debutSeance = Carbon::parse("{$dateStr} {$lockedCreneau->heure_debut}");
            if (now()->lessThan($debutSeance)) {
                throw new StatutReservationInvalideException("Un litige ne peut pas être ouvert avant le début de la séance. Une annulation directe doit être effectuée (RG10).");
            }

            // RG14 : Vérifier qu'un litige n'existe pas déjà (1-to-1 unique)
            $litigeExistant = Litige::where('reservation_id', $lockedReservation->id)->exists();
            if ($litigeExistant) {
                throw new StatutReservationInvalideException("Un litige a déjà été ouvert pour cette réservation (RG14).");
            }

            // Basculer la réservation en 'en_litige' (les crédits restent séquestrés, RG12)
            $lockedReservation->statut = 'en_litige';
            $lockedReservation->save();

            // Créer le litige
            $litige = new Litige();
            $litige->forceFill([
                'reservation_id' => $lockedReservation->id,
                'declarant_id' => $declarant->id,
                'admin_id' => null,
                'statut' => 'ouvert',
                'decision' => null,
                'description' => $description,
                'motif_decision' => null,
                'date_resolution' => null,
            ]);
            $litige->save();

            return $litige->fresh(['reservation.creneau.offre.intervenant', 'declarant']);
        }, 3);
    }

    /**
     * Contrôle les conflits d'horaires sur la base d'intervalles complets DateTime (RG08).
     * Vérifie pour le participant (qu'il soit bénéficiaire ou intervenant) qu'aucune réservation
     * acceptée, réalisée ou en litige ne chevauche la plage [debut, fin[.
     * Deux séances consécutives (debut == autreFin ou fin == autreDebut) sont formellement autorisées.
     *
     * @param  int       $userId
     * @param  Creneau   $creneau
     * @param  int?      $exclureReservationId
     * @return void
     *
     * @throws ConflitHoraireException
     */
    public function verifierConflitHoraire(int $userId, Creneau $creneau, ?int $exclureReservationId = null): void
    {
        $dateStr = is_string($creneau->date) ? $creneau->date : $creneau->date->format('Y-m-d');
        $debut = Carbon::parse("{$dateStr} {$creneau->heure_debut}");
        $fin = Carbon::parse("{$dateStr} {$creneau->heure_fin}");
        if ($creneau->heure_fin < $creneau->heure_debut) {
            $fin->addDay();
        }

        // RG08 : Rechercher toutes les réservations actives/confirmées pour cet étudiant
        $reservationsExistantes = Reservation::whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
            ->when($exclureReservationId, fn($q) => $q->where('id', '!=', $exclureReservationId))
            ->where(function ($query) use ($userId) {
                $query->where('beneficiaire_id', $userId)
                    ->orWhereHas('creneau.offre', function ($q) use ($userId) {
                        $q->where('etudiant_id', $userId);
                    });
            })
            ->with(['creneau.offre'])
            ->get();

        foreach ($reservationsExistantes as $existante) {
            $autreCreneau = $existante->creneau;
            if (!$autreCreneau) {
                continue;
            }

            $autreDateStr = is_string($autreCreneau->date) ? $autreCreneau->date : $autreCreneau->date->format('Y-m-d');
            
            // Ne comparer que si les séances se déroulent le même jour
            if (substr($autreDateStr, 0, 10) !== substr($dateStr, 0, 10)) {
                continue;
            }

            $autreDebut = Carbon::parse("{$autreDateStr} {$autreCreneau->heure_debut}");
            $autreFin = Carbon::parse("{$autreDateStr} {$autreCreneau->heure_fin}");
            if ($autreCreneau->heure_fin < $autreCreneau->heure_debut) {
                $autreFin->addDay();
            }

            // Chevauchement strict : debut < autreFin ET fin > autreDebut
            // Les séances consécutives (debut == autreFin ou fin == autreDebut) sont autorisées
            if ($debut->lessThan($autreFin) && $fin->greaterThan($autreDebut)) {
                throw new ConflitHoraireException(
                    "Conflit de planning détecté : l'étudiant est déjà engagé sur une séance de {$autreDebut->format('H:i')} à {$autreFin->format('H:i')} le {$dateStr} (RG08)."
                );
            }
        }
    }

    /**
     * Récupère la liste paginée des réservations concernant l'utilisateur (bénéficiaire ou intervenant).
     *
     * @param  User   $user
     * @param  array  $filtres  ['role', 'statut']
     * @param  int    $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getMesReservations(User $user, array $filtres = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $role = $filtres['role'] ?? 'tous';
        $statut = $filtres['statut'] ?? null;

        $query = Reservation::with(['creneau.offre.intervenant', 'beneficiaire', 'litige'])
            ->orderBy('date_demande', 'desc');

        if ($role === 'beneficiaire') {
            $query->where('beneficiaire_id', $user->id);
        } elseif ($role === 'intervenant') {
            $query->whereHas('creneau.offre', fn($q) => $q->where('etudiant_id', $user->id));
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('beneficiaire_id', $user->id)
                    ->orWhereHas('creneau.offre', fn($sub) => $sub->where('etudiant_id', $user->id));
            });
        }

        if (!empty($statut)) {
            $query->where('statut', $statut);
        }

        return $query->paginate($perPage);
    }

    /**
     * Vérifie que le compte de l'utilisateur n'est pas désactivé (RG18).
     *
     * @param  User  $user
     * @return void
     *
     * @throws CompteDesactiveException
     */
    protected function verifierCompteActif(User $user): void
    {
        if (!$user->isActif()) {
            throw new CompteDesactiveException("Votre compte est désactivé (RG18). Aucune opération n'est autorisée.");
        }
    }
}
