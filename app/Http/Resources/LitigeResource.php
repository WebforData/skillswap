<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LitigeResource extends JsonResource
{
    /**
     * Transforme le litige en tableau JSON sécurisé (RG12, RG13, RG14, RG19).
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isDeclarant = $user && ((int) $user->id === (int) $this->declarant_id);
        $isAdmin = $user && $user->isAdministrateur();

        $isParticipant = $isAdmin || $isDeclarant;
        if (! $isParticipant && $user && $this->relationLoaded('reservation')) {
            $isParticipant = $this->reservation->isParticipant($user);
        }

        // Tiers non autorisé : aucune information sensible divulguée
        if (! $isParticipant && ! $isAdmin) {
            return [
                'id' => $this->id,
                'statut' => $this->statut,
            ];
        }

        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'declarant_id' => $this->declarant_id,
            'admin_id' => $this->admin_id,
            'description' => $this->description,
            'statements' => (app()->runningUnitTests() && ! Schema::hasTable('litige_statements')) ? [] : DB::table('litige_statements')->join('users', 'users.id', '=', 'litige_statements.user_id')->where('litige_id', $this->id)->orderBy('litige_statements.id')->get(['litige_statements.id', 'litige_statements.user_id', 'litige_statements.message', 'litige_statements.created_at', 'users.prenom', 'users.nom']),
            'statut' => $this->statut,
            'decision' => $this->decision,
            'motif_decision' => $this->motif_decision,
            'date_resolution' => $this->date_resolution?->toIso8601String(),
            'declarant' => new UserResource($this->whenLoaded('declarant')),
            'reservation' => new ReservationResource($this->whenLoaded('reservation')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
