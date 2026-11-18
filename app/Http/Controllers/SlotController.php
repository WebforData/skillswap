<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Slot;
use App\Http\Requests\SlotRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SlotController extends Controller
{
    public function store(SlotRequest $request, Offer $offer)
    {
        if ($offer->user_id !== Auth::id()) {
            abort(403);
        }

        $dateDebut = Carbon::parse($request->date_debut);
        // RG02: Durée de 1 ou 2 heures
        $dateFin = (clone $dateDebut)->addHours($offer->duree);

        Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'lieu_ou_lien' => $request->lieu_ou_lien, // RG19: privé
            'status' => 'disponible',
        ]);

        return back()->with('success', "Le créneau du {$dateDebut->format('d/m/Y H:i')} au {$dateFin->format('H:i')} a été ajouté avec succès.");
    }

    public function destroy(Slot $slot)
    {
        if ($slot->offer->user_id !== Auth::id()) {
            abort(403);
        }

        // Si le créneau est déjà réservé par une séance acceptée, interdire la suppression directe
        if ($slot->status === 'reserve') {
            return back()->with('error', "Ce créneau fait l'objet d'une séance acceptée. Veuillez annuler la réservation au préalable.");
        }

        $slot->delete();

        return back()->with('success', "Le créneau a été supprimé.");
    }
}
