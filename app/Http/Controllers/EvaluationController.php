<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Evaluation;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EvaluationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $received = $user->evaluationsReceived()->with('evaluator', 'reservation.slot.offer')->latest()->paginate(10, ['*'], 'received_page');
        $given = $user->evaluationsGiven()->with('evaluated', 'reservation.slot.offer')->latest()->paginate(10, ['*'], 'given_page');

        return view('reviews.index', compact('received', 'given', 'user'));
    }

    public function create(Request $request)
    {
        $reservationId = $request->get('reservation');
        $reservation = Reservation::with(['slot.offer.user', 'beneficiary'])->findOrFail($reservationId);
        $user = Auth::user();

        // RG15: Seules les réservations réalisées (completed) donnent droit à évaluation
        if ($reservation->status !== 'completed') {
            return redirect()->route('reservations.index')
                ->with('error', "Une évaluation n'est possible qu'après une séance entièrement validée et réalisée (RG15).");
        }

        $isBeneficiary = ($user->id === $reservation->beneficiary_id);
        $isFormateur = ($user->id === $reservation->slot->offer->user_id);

        if (!$isBeneficiary && !$isFormateur) {
            abort(403);
        }

        // RG15: Une seule évaluation par participant
        $existing = Evaluation::where('reservation_id', $reservation->id)
            ->where('evaluator_id', $user->id)
            ->first();

        if ($existing) {
            return redirect()->route('reviews.index')
                ->with('info', "Vous avez déjà soumis votre évaluation pour cette séance (RG15).");
        }

        $evaluatedUser = $isBeneficiary ? $reservation->slot->offer->user : $reservation->beneficiary;

        return view('reviews.create', compact('reservation', 'evaluatedUser'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'reservation_id' => ['required', 'exists:reservations,id'],
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [
            'note.between' => "La note doit être comprise entre 1 et 5 étoiles (RG15).",
        ]);

        $reservation = Reservation::findOrFail($request->reservation_id);
        $user = Auth::user();

        if ($reservation->status !== 'completed') {
            return back()->with('error', "La séance doit être clôturée comme réalisée pour être évaluée (RG15).");
        }

        $isBeneficiary = ($user->id === $reservation->beneficiary_id);
        $isFormateur = ($user->id === $reservation->slot->offer->user_id);

        if (!$isBeneficiary && !$isFormateur) {
            abort(403);
        }

        $evaluatedId = $isBeneficiary ? $reservation->slot->offer->user_id : $reservation->beneficiary_id;

        // RG15: Vérification d'unicité
        $exists = Evaluation::where('reservation_id', $reservation->id)
            ->where('evaluator_id', $user->id)
            ->exists();

        if ($exists) {
            return redirect()->route('reviews.index')
                ->with('warning', "Vous avez déjà déposé une évaluation pour cette réservation (RG15).");
        }

        Evaluation::create([
            'reservation_id' => $reservation->id,
            'evaluator_id' => $user->id,
            'evaluated_id' => $evaluatedId,
            'note' => $request->note,
            'commentaire' => $request->commentaire,
        ]);

        Notification::create([
            'user_id' => $evaluatedId,
            'titre' => 'Nouvelle évaluation reçue !',
            'message' => "{$user->nom_complet} vous a attribué {$request->note}/5 pour la séance '{$reservation->slot->offer->titre}'.",
            'link' => route('profile.show'),
        ]);

        return redirect()->route('reviews.index')
            ->with('success', "Votre évaluation a été publiée avec succès ! Merci de contribuer à la réputation bienveillante du campus.");
    }
}
