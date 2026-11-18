<?php

namespace App\Services;

use App\Exceptions\OperationFinanciereException;
use App\Exceptions\PortefeuilleIntrouvableException;
use App\Exceptions\SoldeInsuffisantException;
use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditService
{
    /**
     * Attribue exactement 2 crédits de bienvenue à un nouvel étudiant (RG01).
     *
     * @param  User  $etudiant
     * @return MouvementCredit
     *
     * @throws OperationFinanciereException
     * @throws PortefeuilleIntrouvableException
     */
    public function attribuerCreditsBienvenue(User $etudiant): MouvementCredit
    {
        return DB::transaction(function () use ($etudiant) {
            // RG01: Réservé exclusivement aux comptes avec le rôle étudiant
            if (!$etudiant->isEtudiant()) {
                throw new OperationFinanciereException("Les crédits de bienvenue sont exclusivement réservés aux étudiants.");
            }

            // Récupère ou instancie le portefeuille de l'étudiant
            $portefeuille = Portefeuille::firstOrCreate(
                ['etudiant_id' => $etudiant->id],
                ['solde_disponible' => 0, 'credits_bloques' => 0]
            );

            // Verrouillage pessimiste pour empêcher les attributions simultanées
            $portefeuille = Portefeuille::where('id', $portefeuille->id)
                ->lockForUpdate()
                ->first();

            if (!$portefeuille) {
                throw new PortefeuilleIntrouvableException("Portefeuille introuvable pour l'étudiant ID: {$etudiant->id}");
            }

            // RG01: Vérification stricte d'attribution unique sous verrou
            $dejaAttribue = MouvementCredit::where('portefeuille_id', $portefeuille->id)
                ->where('type', 'bienvenue')
                ->exists();

            if ($dejaAttribue) {
                throw new OperationFinanciereException("Les 2 crédits de bienvenue ont déjà été attribués à cet étudiant (RG01).");
            }

            // RG01 & RG16: Crédit du solde disponible et enregistrement immuable
            $portefeuille->solde_disponible += 2;
            $portefeuille->save();

            $mouvement = new MouvementCredit();
            $mouvement->forceFill([
                'portefeuille_id' => $portefeuille->id,
                'reservation_id' => null,
                'quantite' => 2,
                'type' => 'bienvenue',
                'date_mouvement' => now(),
            ]);
            $mouvement->save();

            return $mouvement;
        });
    }

    /**
     * Vérifie si un étudiant dispose d'un solde suffisant pour une opération (RG05).
     *
     * @param  User  $etudiant
     * @param  int   $montant
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function verifierSoldeDisponible(User $etudiant, int $montant): bool
    {
        if ($montant <= 0) {
            throw new InvalidArgumentException("Le montant à vérifier doit être strictement supérieur à zéro.");
        }

        // Lecture directe depuis la base pour éviter le cache Eloquent
        $portefeuille = Portefeuille::where('etudiant_id', $etudiant->id)->first();

        if (!$portefeuille) {
            return false;
        }

        return $portefeuille->solde_disponible >= $montant;
    }

    /**
     * Bloque le coût de la séance sur le compte du bénéficiaire lors de l'acceptation (RG06).
     *
     * @param  Reservation  $reservation
     * @return MouvementCredit
     *
     * @throws SoldeInsuffisantException
     * @throws OperationFinanciereException
     * @throws PortefeuilleIntrouvableException
     */
    public function bloquerCredits(Reservation $reservation): MouvementCredit
    {
        return DB::transaction(function () use ($reservation) {
            // 1. Verrouiller la réservation avec lockForUpdate() et recharger ses données fraîches
            $lockedReservation = Reservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Valider le coût à partir de la réservation verrouillée en base
            $montant = $this->validerCoutReservation($lockedReservation);

            // 3. RG09 : Vérifier que le statut de la réservation permet l'acceptation et le blocage
            if (!in_array($lockedReservation->statut, ['en_attente', 'acceptee'])) {
                throw new OperationFinanciereException("Impossible de bloquer les crédits : le statut actuel de la réservation ('{$lockedReservation->statut}') est incompatible avec le blocage (RG09).");
            }

            // 4. Verrouiller le portefeuille du bénéficiaire
            $portefeuille = Portefeuille::where('etudiant_id', $lockedReservation->beneficiaire_id)
                ->lockForUpdate()
                ->first();

            if (!$portefeuille) {
                throw new PortefeuilleIntrouvableException("Portefeuille introuvable pour le bénéficiaire ID: {$lockedReservation->beneficiaire_id}");
            }

            // 5. Empêcher tout double blocage pour la même réservation sous verrou
            $dejaBloque = MouvementCredit::where('portefeuille_id', $portefeuille->id)
                ->where('reservation_id', $lockedReservation->id)
                ->where('type', 'blocage')
                ->exists();

            if ($dejaBloque) {
                throw new OperationFinanciereException("Les crédits sont déjà bloqués pour cette réservation.");
            }

            // 6. RG05 & RG16 : Vérification du solde disponible
            if ($portefeuille->solde_disponible < $montant) {
                throw new SoldeInsuffisantException("Solde disponible insuffisant ({$portefeuille->solde_disponible} crédits disponibles, {$montant} requis) (RG05).");
            }

            // 7. RG06 : Déplacement du disponible vers les crédits bloqués
            $portefeuille->solde_disponible -= $montant;
            $portefeuille->credits_bloques += $montant;
            $portefeuille->save();

            $mouvement = new MouvementCredit();
            $mouvement->forceFill([
                'portefeuille_id' => $portefeuille->id,
                'reservation_id' => $lockedReservation->id,
                'quantite' => -$montant, // Débit du disponible
                'type' => 'blocage',
                'date_mouvement' => now(),
            ]);
            $mouvement->save();

            return $mouvement;
        });
    }

    /**
     * Libère les crédits bloqués au bénéficiaire suite à une annulation autorisée (RG10).
     *
     * @param  Reservation  $reservation
     * @return MouvementCredit
     *
     * @throws OperationFinanciereException
     * @throws PortefeuilleIntrouvableException
     */
    public function libererCredits(Reservation $reservation): MouvementCredit
    {
        return DB::transaction(function () use ($reservation) {
            // 1. Verrouille la réservation avec lockForUpdate() et recharge ses données
            $lockedReservation = Reservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Valider le coût à partir de la réservation verrouillée en base
            $montant = $this->validerCoutReservation($lockedReservation);

            // Rechargement des relations nécessaires
            $lockedReservation->load('creneau');

            // 3. RG10 : Autoriser la libération UNIQUEMENT pour une réservation acceptée (ou marquée annulée)
            // Une demande en attente, refusée ou clôturée ne donne pas droit à libération directe
            if (!in_array($lockedReservation->statut, ['acceptee', 'annulee'])) {
                throw new OperationFinanciereException("La libération directe des crédits n'est autorisée que pour une réservation acceptée (RG10). Statut actuel : '{$lockedReservation->statut}'.");
            }

            // 4. RG10 : Refuser strictement la libération après le début de la séance
            $creneau = $lockedReservation->creneau;
            if ($creneau) {
                $dateStr = is_string($creneau->date) ? $creneau->date : $creneau->date->format('Y-m-d');
                $debutSeance = Carbon::parse("{$dateStr} {$creneau->heure_debut}");
                if (now()->greaterThanOrEqualTo($debutSeance)) {
                    throw new OperationFinanciereException("L'annulation et la libération directe des crédits sont interdites après le début de la séance (RG10). Un litige doit être ouvert.");
                }
            }

            // 5. Verrouillage du portefeuille du bénéficiaire
            $portefeuille = Portefeuille::where('etudiant_id', $lockedReservation->beneficiaire_id)
                ->lockForUpdate()
                ->first();

            if (!$portefeuille) {
                throw new PortefeuilleIntrouvableException("Portefeuille introuvable pour le bénéficiaire ID: {$lockedReservation->beneficiaire_id}");
            }

            // 6. Vérifie qu'un blocage existe réellement pour cette séance
            $blocageExiste = MouvementCredit::where('portefeuille_id', $portefeuille->id)
                ->where('reservation_id', $lockedReservation->id)
                ->where('type', 'blocage')
                ->exists();

            if (!$blocageExiste) {
                throw new OperationFinanciereException("Impossible de débloquer : aucun crédit n'a été bloqué pour cette réservation.");
            }

            // 7. Refuser toute deuxième libération
            $dejaLibere = MouvementCredit::where('reservation_id', $lockedReservation->id)
                ->where('type', 'deblocage_annulation')
                ->exists();

            if ($dejaLibere) {
                throw new OperationFinanciereException("Les crédits de cette réservation ont déjà été libérés.");
            }

            // 8. RG14 : Vérifier qu'aucun transfert ou remboursement définitif n'a déjà été réalisé
            $dejaRegle = MouvementCredit::where('reservation_id', $lockedReservation->id)
                ->whereIn('type', ['transfert_gain', 'restitution_litige'])
                ->exists();

            if ($dejaRegle) {
                throw new OperationFinanciereException("Cette réservation a déjà fait l'objet d'un règlement financier définitif (RG14).");
            }

            // 9. RG16 : Contrôle anti-solde négatif
            if ($portefeuille->credits_bloques < $montant) {
                throw new OperationFinanciereException("Incohérence financière : le montant bloqué ({$portefeuille->credits_bloques}) est inférieur au coût à libérer ({$montant}).");
            }

            // 10. RG10 : Restitution intégrale des crédits bloqués vers le solde disponible
            $portefeuille->credits_bloques -= $montant;
            $portefeuille->solde_disponible += $montant;
            $portefeuille->save();

            $mouvement = new MouvementCredit();
            $mouvement->forceFill([
                'portefeuille_id' => $portefeuille->id,
                'reservation_id' => $lockedReservation->id,
                'quantite' => $montant, // Restitution positive au disponible
                'type' => 'deblocage_annulation',
                'date_mouvement' => now(),
            ]);
            $mouvement->save();

            return $mouvement;
        });
    }

    /**
     * Transfère définitivement les crédits de la séance :
     * - Débite les crédits bloqués du bénéficiaire (quantité négative)
     * - Crédite le solde disponible de l'intervenant (quantité positive)
     * - Enregistre les deux mouvements dans la même transaction (RG11, RG14).
     *
     * Hiérarchie de verrouillage stable anti-deadlock :
     * 1. Reservation
     * 2. Litige
     * 3. Portefeuilles (ordonnés par ID croissant)
     *
     * @param  Reservation  $reservation
     * @return MouvementCredit  Retourne le mouvement créditeur de l'intervenant
     *
     * @throws OperationFinanciereException
     * @throws PortefeuilleIntrouvableException
     */
    public function transfererCredits(Reservation $reservation): MouvementCredit
    {
        return DB::transaction(function () use ($reservation) {
            // 1. Verrouiller la réservation en premier (Ordre 1) et recharger les données
            $lockedReservation = Reservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Valider le coût à partir de la réservation verrouillée en base
            $montant = $this->validerCoutReservation($lockedReservation);

            // Recharger les relations nécessaires
            $lockedReservation->load('creneau.offre');

            // 3. Verrouiller également la ligne du litige si elle existe (Ordre 2)
            $lockedLitige = Litige::where('reservation_id', $lockedReservation->id)
                ->lockForUpdate()
                ->first();

            // 4. RG12 & RG13 : Recharger et vérifier l'état actuel du litige sous verrou
            if ($lockedLitige && $lockedLitige->statut === 'ouvert') {
                throw new OperationFinanciereException("Transfert impossible : un litige est actuellement ouvert pour cette réservation (RG12, RG13).");
            }

            // Statut de réservation incompatible avec un transfert
            if (in_array($lockedReservation->statut, ['refusee', 'annulee'])) {
                throw new OperationFinanciereException("Impossible de transférer les crédits pour une réservation annulée ou refusée.");
            }

            // 5. RG14 : Vérifier sous verrou qu'aucun règlement définitif n'existe déjà
            $dejaRegle = MouvementCredit::where('reservation_id', $lockedReservation->id)
                ->whereIn('type', ['transfert_gain', 'restitution_litige', 'deblocage_annulation'])
                ->exists();

            if ($dejaRegle) {
                throw new OperationFinanciereException("Un règlement financier définitif a déjà été exécuté pour cette réservation (RG14).");
            }

            // 6. RG11 & RG13 : Conditions de transfert basées sur la décision fraîche du litige verrouillé
            $arbitrageFavorable = $lockedLitige
                && $lockedLitige->statut === 'resolu'
                && $lockedLitige->decision === 'transfert_intervenant';

            if (!$arbitrageFavorable && !$lockedReservation->isDoublementConfirmee()) {
                throw new OperationFinanciereException("Le transfert exige la confirmation des deux participants ou une décision arbitrale favorable à l'intervenant (RG11, RG13).");
            }

            // Identification de l'intervenant
            $intervenantId = $lockedReservation->getIntervenantIdAttribute();
            if (!$intervenantId) {
                throw new OperationFinanciereException("Impossible d'identifier l'intervenant associé à cette réservation.");
            }

            // 7. Verrouiller les portefeuilles concernés dans un ordre stable par ID croissant (Ordre 3)
            $beneficiaireWallet = Portefeuille::where('etudiant_id', $lockedReservation->beneficiaire_id)->first();
            $intervenantWallet = Portefeuille::where('etudiant_id', $intervenantId)->first();

            if (!$beneficiaireWallet || !$intervenantWallet) {
                throw new PortefeuilleIntrouvableException("L'un des portefeuilles requis pour le transfert est introuvable.");
            }

            $wallets = Portefeuille::whereIn('id', [$beneficiaireWallet->id, $intervenantWallet->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedBeneficiaire = $wallets->get($beneficiaireWallet->id);
            $lockedIntervenant = $wallets->get($intervenantWallet->id);

            // Vérifier qu'un blocage préalable existe
            $blocageExiste = MouvementCredit::where('portefeuille_id', $lockedBeneficiaire->id)
                ->where('reservation_id', $lockedReservation->id)
                ->where('type', 'blocage')
                ->exists();

            if (!$blocageExiste) {
                throw new OperationFinanciereException("Impossible de transférer : aucun crédit n'a été préalablement bloqué pour cette réservation.");
            }

            // RG16 : Contrôle que les crédits bloqués suffisent
            if ($lockedBeneficiaire->credits_bloques < $montant) {
                throw new OperationFinanciereException("Incohérence : le bénéficiaire ne possède pas suffisamment de crédits bloqués ({$lockedBeneficiaire->credits_bloques} pour {$montant} requis).");
            }

            // 8. Opération financière bilatérale
            $lockedBeneficiaire->credits_bloques -= $montant;
            $lockedBeneficiaire->save();

            $lockedIntervenant->solde_disponible += $montant;
            $lockedIntervenant->save();

            // 9. Enregistrer OBLIGATOIREMENT les deux mouvements opposés dans la même transaction SQL
            // Mouvement 1 : Débit des crédits bloqués du bénéficiaire (quantité négative)
            $mvtDebitBeneficiaire = new MouvementCredit();
            $mvtDebitBeneficiaire->forceFill([
                'portefeuille_id' => $lockedBeneficiaire->id,
                'reservation_id' => $lockedReservation->id,
                'quantite' => -$montant,
                'type' => 'transfert_gain',
                'date_mouvement' => now(),
            ]);
            $mvtDebitBeneficiaire->save();

            // Mouvement 2 : Crédit du disponible de l'intervenant (quantité positive)
            $mvtCreditIntervenant = new MouvementCredit();
            $mvtCreditIntervenant->forceFill([
                'portefeuille_id' => $lockedIntervenant->id,
                'reservation_id' => $lockedReservation->id,
                'quantite' => $montant,
                'type' => 'transfert_gain',
                'date_mouvement' => now(),
            ]);
            $mvtCreditIntervenant->save();

            return $mvtCreditIntervenant;
        });
    }

    /**
     * Rembourse intégralement les crédits bloqués au bénéficiaire suite à un arbitrage de litige (RG13, RG14).
     *
     * Hiérarchie de verrouillage :
     * 1. Reservation
     * 2. Litige
     * 3. Portefeuille bénéficiaire
     *
     * @param  Reservation  $reservation
     * @return MouvementCredit
     *
     * @throws OperationFinanciereException
     * @throws PortefeuilleIntrouvableException
     */
    public function rembourserCredits(Reservation $reservation): MouvementCredit
    {
        return DB::transaction(function () use ($reservation) {
            // 1. Verrouiller la réservation en premier (Ordre 1) et recharger ses données
            $lockedReservation = Reservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Valider le coût à partir de la réservation verrouillée en base
            $montant = $this->validerCoutReservation($lockedReservation);

            // 3. Verrouiller également la ligne du litige (Ordre 2)
            $lockedLitige = Litige::where('reservation_id', $lockedReservation->id)
                ->lockForUpdate()
                ->first();

            // 4. RG13 : Vérifier l'état actuel et la décision fraîche du litige sous verrou
            if (
                !$lockedLitige
                || $lockedLitige->statut !== 'resolu'
                || $lockedLitige->decision !== 'restitution_beneficiaire'
            ) {
                throw new OperationFinanciereException("Le remboursement exige une décision arbitrale résolue de type 'restitution_beneficiaire' (RG13).");
            }

            // 5. RG14 : Vérifier sous verrou qu'aucun règlement définitif n'existe déjà
            $dejaRegle = MouvementCredit::where('reservation_id', $lockedReservation->id)
                ->whereIn('type', ['transfert_gain', 'restitution_litige', 'deblocage_annulation'])
                ->exists();

            if ($dejaRegle) {
                throw new OperationFinanciereException("Un règlement financier définitif a déjà été exécuté pour cette réservation (RG14).");
            }

            // 6. Verrouiller le portefeuille du bénéficiaire (Ordre 3)
            $portefeuille = Portefeuille::where('etudiant_id', $lockedReservation->beneficiaire_id)
                ->lockForUpdate()
                ->first();

            if (!$portefeuille) {
                throw new PortefeuilleIntrouvableException("Portefeuille introuvable pour le bénéficiaire ID: {$lockedReservation->beneficiaire_id}");
            }

            // Vérifier qu'un blocage préalable existe
            $blocageExiste = MouvementCredit::where('portefeuille_id', $portefeuille->id)
                ->where('reservation_id', $lockedReservation->id)
                ->where('type', 'blocage')
                ->exists();

            if (!$blocageExiste) {
                throw new OperationFinanciereException("Impossible de rembourser : aucun crédit n'a été préalablement bloqué pour cette réservation.");
            }

            // 7. RG16 : Contrôle de disponibilité des crédits bloqués
            if ($portefeuille->credits_bloques < $montant) {
                throw new OperationFinanciereException("Incohérence : crédits bloqués insuffisants pour remboursement ({$portefeuille->credits_bloques} pour {$montant}).");
            }

            // 8. Restitution intégrale des crédits bloqués vers le solde disponible
            $portefeuille->credits_bloques -= $montant;
            $portefeuille->solde_disponible += $montant;
            $portefeuille->save();

            // 9. Enregistrement du mouvement
            $mouvement = new MouvementCredit();
            $mouvement->forceFill([
                'portefeuille_id' => $portefeuille->id,
                'reservation_id' => $lockedReservation->id,
                'quantite' => $montant, // Restitution positive
                'type' => 'restitution_litige',
                'date_mouvement' => now(),
            ]);
            $mouvement->save();

            return $mouvement;
        });
    }

    /**
     * Récupère le détail du solde en temps réel d'un étudiant.
     *
     * @param  User  $etudiant
     * @return array
     *
     * @throws PortefeuilleIntrouvableException
     */
    public function getSolde(User $etudiant): array
    {
        // Interroge directement la base pour éviter toute valeur en cache d'une relation Eloquent
        $portefeuille = Portefeuille::where('etudiant_id', $etudiant->id)->first();

        if (!$portefeuille) {
            throw new PortefeuilleIntrouvableException("Aucun portefeuille associé à cet utilisateur.");
        }

        return [
            'solde_disponible' => $portefeuille->solde_disponible,
            'credits_bloques' => $portefeuille->credits_bloques,
            'solde_total' => $portefeuille->solde_total,
        ];
    }

    /**
     * Valide et extrait le coût en crédits de la réservation.
     *
     * @param  Reservation  $reservation
     * @return int
     *
     * @throws OperationFinanciereException
     */
    private function validerCoutReservation(Reservation $reservation): int
    {
        $montant = (int) $reservation->cout_credits;

        // RG02 : Le coût d'une séance est strictement de 1 ou 2 crédits
        if ($montant !== 1 && $montant !== 2) {
            throw new OperationFinanciereException("Le coût en crédits de la réservation ({$montant}) est invalide. Il doit être de 1 ou 2 crédits (RG02).");
        }

        return $montant;
    }
}
