<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ReservationController extends Controller
{
    protected ReservationService $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Mes demandes et réservations (Envoyées & Reçues)
     */
    public function index()
    {
        $user = Auth::user();

        // 1. Demandes envoyées par l'utilisateur (en tant que bénéficiaire)
        $sentReservations = Reservation::where('beneficiary_id', $user->id)
            ->with(['slot.offer.user'])
            ->latest()
            ->paginate(10, ['*'], 'sent_page');

        // 2. Demandes reçues par l'utilisateur (en tant que formateur)
        $receivedReservations = Reservation::whereHas('slot.offer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->with(['beneficiary', 'slot.offer'])
        ->latest()
        ->paginate(10, ['*'], 'received_page');

        return view('reservations.index', compact('sentReservations', 'receivedReservations'));
    }

    /**
     * RG03, RG04, RG05: Demander une réservation
     */
    public function store(Slot $slot)
    {
        try {
            $reservation = $this->reservationService->createPendingReservation(Auth::user(), $slot->id);

            return redirect()->route('reservations.index')
                ->with('success', "Votre demande de réservation pour la séance '{$slot->offer->titre}' a été transmise à l'intervenant. Aucun crédit n'est bloqué tant que la demande n'est pas acceptée (RG05).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * RG06, RG07, RG08: Accepter une demande (formateur)
     */
    public function accept(Reservation $reservation)
    {
        try {
            $this->reservationService->acceptReservation($reservation, Auth::user());

            return back()->with('success', "La demande a été acceptée avec succès ! Les crédits correspondants ont été bloqués sur le compte du bénéficiaire (RG06).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * RG09: Refuser une demande avec motif (formateur)
     */
    public function reject(Request $request, Reservation $reservation)
    {
        $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'motif.required' => "Veuillez préciser un motif explicite de refus (RG09).",
        ]);

        try {
            $this->reservationService->rejectReservation($reservation, Auth::user(), $request->motif);

            return back()->with('info', "La demande a été refusée et le motif a été transmis au participant.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * RG10: Annuler une séance acceptée avant le début
     */
    public function cancel(Request $request, Reservation $reservation)
    {
        $request->validate([
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->reservationService->cancelAcceptedReservation($reservation, Auth::user(), $request->motif ?? '');

            return back()->with('success', "La séance a été annulée. Les crédits bloqués ont été immédiatement restitués au bénéficiaire (RG10).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
