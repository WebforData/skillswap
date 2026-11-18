<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transforme l'utilisateur en tableau JSON sécurisé (RG19, OWASP).
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isSelf = $user && ((int) $user->id === (int) $this->id);
        $isAdmin = $user && $user->isAdministrateur();

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => "{$this->prenom} {$this->nom}",
            'filiere' => $this->filiere,
            'competences_proposees' => $this->competences_proposees,
            'competences_recherchees' => $this->competences_recherchees,
            'role' => $this->role,
            'statut' => $this->statut,
            // L'email et le portefeuille ne sont visibles que par l'utilisateur lui-même ou un administrateur (RG16, RG19)
            'email' => $this->when($isSelf || $isAdmin, $this->email),
            'portefeuille' => $this->when(
                ($isSelf || $isAdmin) && $this->relationLoaded('portefeuille') && $this->portefeuille !== null,
                fn() => new PortefeuilleResource($this->portefeuille)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
