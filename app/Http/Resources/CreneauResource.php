<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreneauResource extends JsonResource
{
    /**
     * Transforme le créneau en tableau JSON avec protection RG19 sur le lien/lieu privé.
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $dateStr = is_string($this->date) ? $this->date : $this->date?->format('Y-m-d');

        // RG19 : Coordonnées privées strictement conditionnées à l'autorisation
        $coordonneesPrivees = $this->peutAccederAuxDetailsPrives($user)
            ? $this->lien_ou_lieu_prive
            : null;

        return [
            'id' => $this->id,
            'offre_id' => $this->offre_id,
            'date' => $dateStr,
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'lieu_public' => $this->lieu_public,
            'lieu_ou_lien_prive' => $coordonneesPrivees, // RG19
            'est_disponible' => $this->when(isset($this->est_disponible), $this->est_disponible),
            'offre' => new OffreResource($this->whenLoaded('offre')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
