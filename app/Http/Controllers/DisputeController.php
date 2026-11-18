<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DisputeController extends Controller
{
    public function myDisputes()
    {
        $user = Auth::user();

        // RG19: Les litiges sont accessibles uniquement à leurs participants et à l'administrateur
        $disputes = Dispute::whereHas('reservation', function ($q) use ($user) {
            $q->where('beneficiary_id', $user->id)
              ->orWhereHas('slot.offer', fn($sub) => $sub->where('user_id', $user->id));
        })
        ->with(['reservation.slot.offer.user', 'reservation.beneficiary', 'openedBy'])
        ->latest()
        ->paginate(10);

        return view('disputes.my', compact('disputes'));
    }

    public function create(Request $request)
    {
        $reservationId = $request->get('reservation');
        $reservation = Reservation::with(['slot.offer.user', 'beneficiary'])->findOrFail($reservationId);
        $user = Auth::user();

        $isBeneficiary = ($user->id === $reservation->beneficiary_id);
        $isFormateur = ($user->id === $reservation->slot->offer->user_id);

        if (!$isBeneficiary && !$isFormateur) {
            abort(403);
        }

        // Vérifier si un litige est déjà ouvert pour cette réservation
        if ($reservation->dispute) {
            return redirect()->route('disputes.my')
                ->with('info', "Un litige est déjà ouvert pour cette réservation.");
        }

        return view('disputes.create', compact('reservation'));
    }

    /**
     * RG12 & RG13: Enregistrer un litige, passer la séance en statut 'dispute', crédits conservés bloqués
     */
    public function store(Request $request)
    {
        $request->validate([
            'reservation_id' => ['required', 'exists:reservations,id'],
            'motif' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
        ], [
            'motif.required' => "Veuillez préciser le motif du litige (ex: Absence, problème technique, désaccord).",
            'description.min' => "Veuillez décrire la situation en détail (au moins 20 caractères) pour l'examen de l'administrateur.",
        ]);

        $reservation = Reservation::findOrFail($request->reservation_id);
        $user = Auth::user();

        $isBeneficiary = ($user->id === $reservation->beneficiary_id);
        $isFormateur = ($user->id === $reservation->slot->offer->user_id);

        if (!$isBeneficiary && !$isFormateur) {
            abort(403);
        }

        DB::transaction(function () use ($reservation, $user, $request) {
            // Passer la réservation en 'dispute' (RG09, RG13)
            $reservation->status = 'dispute';
            $reservation->save();

            $dispute = Dispute::create([
                'reservation_id' => $reservation->id,
                'opened_by_id' => $user->id,
                'motif' => $request->motif,
                'description' => $request->description,
                'resolution' => 'en_cours',
            ]);

            // Alerte pour les administrateurs
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'titre' => 'Nouveau litige déclaré',
                    'message' => "Un litige a été ouvert par {$user->nom_complet} sur la séance '{$reservation->slot->offer->titre}'. Décision d'arbitrage requise.",
                    'link' => route('admin.disputes.index'),
                ]);
            }
        });

        return redirect()->route('disputes.my')
            ->with('warning', "Votre litige a été ouvert. Les crédits restent bloqués en sécurité jusqu'à la décision définitive de l'administrateur (RG13).");
    }
}
