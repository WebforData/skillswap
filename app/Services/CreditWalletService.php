<?php

namespace App\Services;

use App\Models\User;
use App\Models\Reservation;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class CreditWalletService
{
    /**
     * RG01: Attribution des 2 crédits de bienvenue lors de l'inscription (une seule fois)
     */
    public function creditWelcomeBonus(User $user): void
    {
        DB::transaction(function () use ($user) {
            CreditTransaction::create([
                'user_id' => $user->id,
                'montant' => 2,
                'type' => 'bienvenue',
                'description' => 'Attribution des 2 crédits gratuits de bienvenue (RG01)',
            ]);
        });
    }

    /**
     * RG05 & RG06: Bloquer les crédits lors de l'acceptation d'une réservation
     */
    public function lockCreditsForReservation(Reservation $reservation): void
    {
        $cout = $reservation->slot->offer->duree; // 1 ou 2 crédits (RG02)

        $beneficiary = User::where('id', $reservation->beneficiary_id)->lockForUpdate()->firstOrFail();

        // RG05 & RG16: Vérification stricte du solde disponible
        if ($beneficiary->solde_disponible < $cout) {
            throw new Exception("Solde disponible insuffisant pour accepter cette séance ({$beneficiary->solde_disponible}h disponible(s), {$cout}h requise(s)).");
        }

        $beneficiary->solde_disponible -= $cout;
        $beneficiary->solde_bloque += $cout;
        $beneficiary->save();

        CreditTransaction::create([
            'user_id' => $beneficiary->id,
            'reservation_id' => $reservation->id,
            'montant' => -$cout,
            'type' => 'blocage',
            'description' => "Blocage de {$cout} crédit(s) pour la séance '{$reservation->slot->offer->titre}'",
        ]);
    }

    /**
     * RG10: Libérer les crédits bloqués lors d'une annulation avant le début
     */
    public function releaseCreditsAfterCancellation(Reservation $reservation, string $motif = ''): void
    {
        $cout = $reservation->slot->offer->duree;

        $beneficiary = User::where('id', $reservation->beneficiary_id)->lockForUpdate()->firstOrFail();

        if ($beneficiary->solde_bloque < $cout) {
            throw new Exception("Incohérence du solde bloqué lors de l'annulation.");
        }

        $beneficiary->solde_bloque -= $cout;
        $beneficiary->solde_disponible += $cout;
        $beneficiary->save();

        CreditTransaction::create([
            'user_id' => $beneficiary->id,
            'reservation_id' => $reservation->id,
            'montant' => $cout,
            'type' => 'liberation',
            'description' => "Libération de {$cout} crédit(s) suite à l'annulation de la séance" . ($motif ? " ({$motif})" : ""),
        ]);
    }

    /**
     * RG11 & RG16: Transférer les crédits du bénéficiaire vers le formateur après les 2 confirmations
     */
    public function transferCreditsAfterCompletion(Reservation $reservation): void
    {
        $cout = $reservation->slot->offer->duree;
        $formateurId = $reservation->slot->offer->user_id;

        // Verrouillage pessimiste des 2 comptes dans l'ordre croissant des IDs pour éviter tout deadlock
        $ids = [$reservation->beneficiary_id, $formateurId];
        sort($ids);

        $users = User::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
        $beneficiary = $users->get($reservation->beneficiary_id);
        $formateur = $users->get($formateurId);

        if ($beneficiary->solde_bloque < $cout) {
            throw new Exception("Incohérence du solde bloqué du bénéficiaire lors du transfert.");
        }

        // Débit des crédits bloqués du bénéficiaire
        $beneficiary->solde_bloque -= $cout;
        $beneficiary->save();

        // Crédit sur le solde disponible du formateur
        $formateur->solde_disponible += $cout;
        $formateur->save();

        // Traçabilité immuable (RG16)
        CreditTransaction::create([
            'user_id' => $beneficiary->id,
            'reservation_id' => $reservation->id,
            'montant' => -$cout,
            'type' => 'transfert_debit',
            'description' => "Règlement de {$cout} crédit(s) à {$formateur->nom_complet} pour la séance '{$reservation->slot->offer->titre}'",
        ]);

        CreditTransaction::create([
            'user_id' => $formateur->id,
            'reservation_id' => $reservation->id,
            'montant' => $cout,
            'type' => 'transfert_credit',
            'description' => "Réception de {$cout} crédit(s) de {$beneficiary->nom_complet} pour l'animation de la séance '{$reservation->slot->offer->titre}'",
        ]);
    }

    /**
     * RG13 & RG14: Arbitrage administratif d'un litige (transfert formateur OU restitution bénéficiaire)
     */
    public function resolveDispute(Reservation $reservation, string $decision, string $decisionAdmin): void
    {
        $cout = $reservation->slot->offer->duree;
        $formateurId = $reservation->slot->offer->user_id;

        if ($decision === 'transfert_formateur') {
            // Transfert intégral au formateur (RG13)
            $users = User::whereIn('id', [$reservation->beneficiary_id, $formateurId])->lockForUpdate()->get()->keyBy('id');
            $beneficiary = $users->get($reservation->beneficiary_id);
            $formateur = $users->get($formateurId);

            if ($beneficiary->solde_bloque < $cout) {
                throw new Exception("Solde bloqué insuffisant pour clôturer le litige.");
            }

            $beneficiary->solde_bloque -= $cout;
            $beneficiary->save();

            $formateur->solde_disponible += $cout;
            $formateur->save();

            CreditTransaction::create([
                'user_id' => $beneficiary->id,
                'reservation_id' => $reservation->id,
                'montant' => -$cout,
                'type' => 'transfert_debit',
                'description' => "Transfert suite à décision administrative : {$decisionAdmin}",
            ]);

            CreditTransaction::create([
                'user_id' => $formateur->id,
                'reservation_id' => $reservation->id,
                'montant' => $cout,
                'type' => 'litige_transfert',
                'description' => "Crédits accordés suite à décision administrative : {$decisionAdmin}",
            ]);

            $reservation->status = 'completed'; // Clôture comme réalisée (RG14)
            $reservation->save();

        } elseif ($decision === 'restitution_beneficiaire') {
            // Restitution intégrale au bénéficiaire (RG13)
            $beneficiary = User::where('id', $reservation->beneficiary_id)->lockForUpdate()->firstOrFail();

            if ($beneficiary->solde_bloque < $cout) {
                throw new Exception("Solde bloqué insuffisant pour la restitution.");
            }

            $beneficiary->solde_bloque -= $cout;
            $beneficiary->solde_disponible += $cout;
            $beneficiary->save();

            CreditTransaction::create([
                'user_id' => $beneficiary->id,
                'reservation_id' => $reservation->id,
                'montant' => $cout,
                'type' => 'litige_remboursement',
                'description' => "Restitution de {$cout} crédit(s) suite à décision administrative : {$decisionAdmin}",
            ]);

            $reservation->status = 'cancelled'; // Clôture comme annulée (RG14)
            $reservation->save();
        } else {
            throw new Exception("Décision de litige invalide.");
        }
    }
}
