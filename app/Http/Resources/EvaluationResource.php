<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    /**
     * Transforme l'évaluation en tableau JSON sécurisé (RG15, RG19).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'auteur_id' => $this->auteur_id,
            'cible_id' => $this->cible_id,
            'note' => (int) $this->note,
            'commentaire' => $this->commentaire,
            'auteur' => new UserResource($this->whenLoaded('auteur')),
            'cible' => new UserResource($this->whenLoaded('cible')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
