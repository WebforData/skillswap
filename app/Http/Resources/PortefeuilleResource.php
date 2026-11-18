<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortefeuilleResource extends JsonResource
{
    /**
     * Transforme le portefeuille en tableau JSON sécurisé (RG16, RG19).
     * Les soldes financiers ne sont exposés qu'à leur propriétaire et aux administrateurs.
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user && ((int) $user->id === (int) $this->etudiant_id);
        $isAdmin = $user && $user->isAdministrateur();

        if (!$isOwner && !$isAdmin) {
            return [
                'etudiant_id' => $this->etudiant_id,
            ];
        }

        return [
            'id' => $this->id,
            'etudiant_id' => $this->etudiant_id,
            'solde_disponible' => (int) $this->solde_disponible,
            'credits_bloques' => (int) $this->credits_bloques,
            'solde_total' => (int) ($this->solde_disponible + $this->credits_bloques),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
