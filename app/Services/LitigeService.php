<?php

namespace App\Services;

use App\Exceptions\AccesReservationNonAutoriseException;
use App\Exceptions\CompteDesactiveException;
use App\Exceptions\DecisionLitigeInvalideException;
use App\Exceptions\LitigeDejaResoluException;
use App\Exceptions\LitigeException;
use App\Exceptions\ReservationNonLitigieuseException;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LitigeService
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * Règle définitivement un litige par décision administrative arbitrale (RG13, RG14).
     *
     * @param  User    $admin
     * @param  int     $litigeId
     * @param  string  $decision  'transfert_intervenant' (ou 'transferer_intervenant') | 'restitution_beneficiaire'
     * @param  string  $motif
     * @return Litige
     *
     * @throws AccesReservationNonAutoriseException
     * @throws CompteDesactiveException
     * @throws DecisionLitigeInvalideException
     * @throws LitigeDejaResoluException
     * @throws ReservationNonLitigieuseException
     * @throws LitigeException
     */
    public function arbitrerLitige(User $admin, int $litigeId, string $decision, string $motif): Litige
    {
        // 1. Vérifier que l'utilisateur est un administrateur légitime
        if (!$admin->isAdministrateur()) {
            throw new AccesReservationNonAutoriseException("Seul un administrateur de la plateforme est habilité à arbitrer les litiges (RG13).");
        }

        // 2. RG18 : Vérifier que le compte de l'administrateur est actif
        if (!$admin->isActif()) {
            throw new CompteDesactiveException("Votre compte administrateur a été désactivé (RG18). Impossible d'arbitrer ce dossier.");
        }

        // 3. Normalisation et validation de la décision arbitrale
        $decisionNormalisee = match (strtolower(trim($decision))) {
            'transfert_intervenant', 'transferer_intervenant' => 'transfert_intervenant',
            'restitution_beneficiaire', 'restituer_beneficiaire' => 'restitution_beneficiaire',
            default => null,
        };

        if (!$decisionNormalisee) {
            throw new DecisionLitigeInvalideException("Décision arbitrale invalide. Les seules options autorisées par RG13 sont 'transfert_intervenant' et 'restitution_beneficiaire'.");
        }

        // 4. Validation du motif d'arbitrage
        $motifNettoye = trim($motif);
        if (empty($motifNettoye)) {
            throw new LitigeException("Le motif de la décision arbitrale est obligatoire.");
        }

        // 5. Exécution dans une transaction SQL atomique avec 3 tentatives en cas de verrouillage transitoire
        return DB::transaction(function () use ($admin, $litigeId, $decisionNormalisee, $motifNettoye) {
            // Identifier le dossier de litige et sa réservation
            $preloadLitige = Litige::findOrFail($litigeId);
            $preloadReservation = Reservation::findOrFail($preloadLitige->reservation_id);

            // Verrouiller Creneau en PREMIER (Ordre canonique 1)
            if ($preloadReservation->creneau_id) {
                Creneau::where('id', $preloadReservation->creneau_id)
                    ->lockForUpdate()
                    ->first();
            }

            // Verrouiller Reservation en SECOND (Ordre canonique 2)
            $lockedReservation = Reservation::where('id', $preloadLitige->reservation_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verrouiller Litige en TROISIÈME (Ordre canonique 3)
            $lockedLitige = Litige::where('id', $litigeId)
                ->lockForUpdate()
                ->firstOrFail();

            // RG14 : Vérifier que le litige est toujours ouvert
            if ($lockedLitige->statut !== 'ouvert') {
                throw new LitigeDejaResoluException("Ce litige est déjà résolu et ne peut faire l'objet d'un second arbitrage (RG14).");
            }

            // Vérifier que la réservation associée est bien en statut 'en_litige'
            if ($lockedReservation->statut !== 'en_litige') {
                throw new ReservationNonLitigieuseException("La réservation associée n'est pas au statut 'en_litige' (statut actuel : '{$lockedReservation->statut}').");
            }

            // RG14 : Vérifier qu'aucun règlement financier définitif n'a déjà eu lieu
            $dejaRegle = MouvementCredit::where('reservation_id', $lockedReservation->id)
                ->whereIn('type', ['transfert_gain', 'restitution_litige', 'deblocage_annulation'])
                ->exists();

            if ($dejaRegle) {
                throw new LitigeException("Cette réservation a déjà fait l'objet d'un dénouement financier définitif (RG14).");
            }

            // Vérifier que les crédits bloqués suffisent sur le portefeuille du bénéficiaire
            $walletBeneficiaire = Portefeuille::where('etudiant_id', $lockedReservation->beneficiaire_id)
                ->lockForUpdate()
                ->first();

            if (!$walletBeneficiaire || $walletBeneficiaire->credits_bloques < $lockedReservation->cout_credits) {
                throw new LitigeException("Incohérence financière : les crédits bloqués du bénéficiaire sont insuffisants pour exécuter l'arbitrage.");
            }

            // Enregistrer la décision administrative sur le litige avant d'invoquer CreditService
            // (CreditService vérifie sous verrou que statut === 'resolu' et decision === 'transfert_intervenant' ou 'restitution_beneficiaire')
            $lockedLitige->admin_id = $admin->id;
            $lockedLitige->decision = $decisionNormalisee;
            $lockedLitige->motif_decision = $motifNettoye;
            $lockedLitige->date_resolution = now();
            $lockedLitige->statut = 'resolu';
            $lockedLitige->save();

            // RG14 : Mise à jour du statut final de la réservation et exécution du mouvement financier approprié
            if ($decisionNormalisee === 'transfert_intervenant') {
                // Décision favorable à l'intervenant :
                // - Les deux confirmations sont validées pour satisfaire le trigger d'intégrité SQL
                // - La réservation passe définitivement à 'realisee'
                $lockedReservation->confirmation_intervenant = true;
                $lockedReservation->confirmation_beneficiaire = true;
                $lockedReservation->date_confirmation_intervenant = $lockedReservation->date_confirmation_intervenant ?? now();
                $lockedReservation->date_confirmation_beneficiaire = $lockedReservation->date_confirmation_beneficiaire ?? now();
                $lockedReservation->statut = 'realisee';
                $lockedReservation->save();

                // Transfert effectif des crédits vers l'intervenant
                $this->creditService->transfererCredits($lockedReservation);
            } else {
                // Décision favorable au bénéficiaire :
                // - La réservation passe définitivement à 'annulee' (RG14)
                $lockedReservation->statut = 'annulee';
                $lockedReservation->motif_refus = "Réservation annulée suite à arbitrage administratif du litige #{$lockedLitige->id} : {$motifNettoye}";
                $lockedReservation->save();

                // Restitution intégrale des crédits bloqués au bénéficiaire
                $this->creditService->rembourserCredits($lockedReservation);
            }

            return $lockedLitige->fresh([
                'reservation.creneau.offre.intervenant',
                'reservation.beneficiaire',
                'declarant',
                'administrateur',
            ]);
        }, 3);
    }
}
