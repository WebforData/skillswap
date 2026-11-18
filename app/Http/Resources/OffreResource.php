<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OffreResource extends JsonResource
{
    /**
     * Transforme l'offre en tableau JSON sécurisé (RG04, RG17).
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user && ((int) $user->id === (int) $this->etudiant_id);
        $isAdmin = $user && $user->isAdministrateur();

        return [
            'id' => $this->id,
            'etudiant_id' => $this->etudiant_id,
            'categorie_id' => $this->categorie_id,
            'titre' => $this->titre,
            'competence' => $this->competence,
            'description' => $this->description,
            'niveau' => $this->niveau,
            'duree' => (int) $this->duree,
            'mode' => $this->mode,
            'statut' => $this->when($isOwner || $isAdmin, $this->statut),
            'categorie' => $this->whenLoaded('categorie', function () {
                return [
                    'id' => $this->categorie->id,
                    'nom' => $this->categorie->nom,
                ];
            }),
            'intervenant' => new UserResource($this->whenLoaded('intervenant')),
            'creneaux' => CreneauResource::collection($this->whenLoaded('creneaux')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
