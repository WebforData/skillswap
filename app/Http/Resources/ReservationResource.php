<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transforme la réservation en tableau JSON sécurisé (RG02-RG19).
     */
    public function toArray(Request $request): array
    {
        $creneau = $this->relationLoaded('creneau') ? $this->creneau : null;
        $offre = $creneau && $creneau->relationLoaded('offre') ? $creneau->offre : null;
        $intervenant = $offre && $offre->relationLoaded('intervenant') ? $offre->intervenant : null;

        return [
            'id' => $this->id,
            'creneau_id' => $this->creneau_id,
            'beneficiaire_id' => $this->beneficiaire_id,
            'statut' => $this->statut,
            'cout_credits' => (int) $this->cout_credits,
            'motif_refus' => $this->motif_refus,
            'confirmation_intervenant' => (bool) $this->confirmation_intervenant,
            'confirmation_beneficiaire' => (bool) $this->confirmation_beneficiaire,
            'date_confirmation_intervenant' => $this->date_confirmation_intervenant?->toIso8601String(),
            'date_confirmation_beneficiaire' => $this->date_confirmation_beneficiaire?->toIso8601String(),
            'date_demande' => $this->date_demande?->toIso8601String(),
            'creneau' => $creneau ? new CreneauResource($creneau) : null,
            'offre' => $offre ? new OffreResource($offre) : null,
            'beneficiaire' => $this->whenLoaded('beneficiaire', fn() => new UserResource($this->beneficiaire)),
            'intervenant' => $intervenant ? new UserResource($intervenant) : null,
            'litige' => $this->whenLoaded('litige', fn() => new LitigeResource($this->litige)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
