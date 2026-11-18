<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Offer;
use App\Services\SuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected SuggestionService $suggestionService;

    public function __construct(SuggestionService $suggestionService)
    {
        $this->suggestionService = $suggestionService;
    }

    public function index()
    {
        $user = Auth::user();

        // 1. Demandes reçues par l'étudiant en tant que formateur et nécessitant une action (statut 'pending')
        $pendingDemandes = Reservation::whereHas('slot.offer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->where('status', 'pending')
        ->with(['beneficiary', 'slot.offer'])
        ->latest()
        ->get();

        // 2. Prochaines séances acceptées (en tant que formateur ou bénéficiaire)
        $upcomingSessions = Reservation::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('beneficiary_id', $user->id)
                  ->orWhereHas('slot.offer', fn($sub) => $sub->where('user_id', $user->id));
            })
            ->whereHas('slot', fn($q) => $q->where('date_fin', '>=', now()))
            ->with(['beneficiary', 'slot.offer.user'])
            ->get()
            ->sortBy(fn($r) => $r->slot->date_debut);

        // 3. Séances terminées en attente de confirmation
        $pendingConfirmations = Reservation::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('beneficiary_id', $user->id)
                  ->orWhereHas('slot.offer', fn($sub) => $sub->where('user_id', $user->id));
            })
            ->whereHas('slot', fn($q) => $q->where('date_fin', '<', now()))
            ->with(['beneficiary', 'slot.offer.user'])
            ->get();

        // 4. Suggestions d'offres personnalisées
        $suggestions = $this->suggestionService->getSuggestionsForUser($user, 4);

        // 5. Mes offres actives
        $myOffersCount = $user->offers()->where('status', 'publiee')->count();

        return view('dashboard', compact(
            'user',
            'pendingDemandes',
            'upcomingSessions',
            'pendingConfirmations',
            'suggestions',
            'myOffersCount'
        ));
    }
}
