<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ConfirmationController extends Controller
{
    protected ReservationService $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * RG11 & RG12: Confirmer la réalisation de la séance
     */
    public function confirm(Reservation $reservation)
    {
        try {
            $transferred = $this->reservationService->confirmReservation($reservation, Auth::user());

            if ($transferred) {
                return redirect()->route('reservations.index')
                    ->with('success', "Les deux confirmations ont été reçues ! La séance est validée et les crédits ont été transférés avec succès (RG11). Vous pouvez désormais évaluer votre pair.");
            }

            return redirect()->route('planning')
                ->with('info', "Votre confirmation a bien été enregistrée. Les crédits restent bloqués en attente de la confirmation de l'autre participant (RG12).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
