<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanningController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Récupérer toutes les réservations acceptées (futures et passées)
        $sessions = Reservation::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('beneficiary_id', $user->id)
                  ->orWhereHas('slot.offer', fn($sub) => $sub->where('user_id', $user->id));
            })
            ->with(['beneficiary', 'slot.offer.user'])
            ->get()
            ->sortBy(fn($r) => $r->slot->date_debut);

        $upcoming = $sessions->filter(fn($r) => $r->slot->date_fin->isFuture());
        $past = $sessions->filter(fn($r) => $r->slot->date_fin->isPast());

        return view('planning.index', compact('upcoming', 'past', 'user'));
    }
}
