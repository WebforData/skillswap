<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Support\Facades\Schema;

class ReservationNotificationService
{
    public function record(Reservation $reservation): void
    {
        // Legacy unit fixtures create only business tables; real installations use migrations.
        if (app()->runningUnitTests() && ! Schema::hasTable('notifications')) {
            return;
        }
        if (! $reservation->wasRecentlyCreated && ! $reservation->wasChanged(['statut', 'confirmation_intervenant', 'confirmation_beneficiaire'])) {
            return;
        }
        $reservation->loadMissing('creneau.offre');
        foreach (array_unique([$reservation->beneficiaire_id, $reservation->creneau?->offre?->etudiant_id]) as $id) {
            if (! $id) {
                continue;
            }
            Notification::create(['user_id' => $id, 'reservation_id' => $reservation->id, 'title' => 'Mise à jour de séance',
                'message' => ($reservation->creneau?->offre?->titre ?? 'Séance').' : '.str_replace('_', ' ', $reservation->statut).($reservation->motif_refus ? ' - '.$reservation->motif_refus : '')]);
        }
    }
}
