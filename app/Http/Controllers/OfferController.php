<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Category;
use App\Models\Reservation;
use App\Http\Requests\OfferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    /**
     * Catalogue public & étudiant avec recherche et filtres
     */
    public function index(Request $request)
    {
        $categories = Category::all();

        $query = Offer::activePublic()
            ->with(['user', 'category', 'availableSlots']);

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('titre', 'LIKE', "%{$q}%")
                    ->orWhere('competence', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->filled('niveau')) {
            $query->where('niveau', $request->get('niveau'));
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->get('mode'));
        }

        if ($request->filled('duree')) {
            $query->where('duree', $request->get('duree'));
        }

        $offers = $query->latest()->paginate(9)->withQueryString();

        return view('offers.index', compact('offers', 'categories'));
    }

    /**
     * Fiche d'une offre
     */
    public function show(Offer $offer)
    {
        // Si l'offre est masquée et qu'on n'est pas admin ni l'auteur, accès interdit
        if ($offer->is_masked && (!Auth::check() || (!Auth::user()->isAdmin() && Auth::id() !== $offer->user_id))) {
            abort(404, "Cette offre n'est plus disponible.");
        }

        $offer->load(['user.evaluationsReceived.evaluator', 'category', 'availableSlots']);

        return view('offers.show', compact('offer'));
    }

    /**
     * Mes offres
     */
    public function myOffers()
    {
        $offers = Auth::user()->offers()
            ->with(['category', 'slots.reservations'])
            ->latest()
            ->paginate(10);

        return view('offers.my', compact('offers'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('offers.create', compact('categories'));
    }

    public function store(OfferRequest $request)
    {
        $offer = Auth::user()->offers()->create($request->validated());

        return redirect()->route('offers.my')
            ->with('success', "Votre offre '{$offer->titre}' a été créée avec succès. Vous pouvez maintenant y ajouter des créneaux !");
    }

    public function edit(Offer $offer)
    {
        if ($offer->user_id !== Auth::id()) {
            abort(403);
        }

        $categories = Category::all();
        return view('offers.edit', compact('offer', 'categories'));
    }

    public function update(OfferRequest $request, Offer $offer)
    {
        if ($offer->user_id !== Auth::id()) {
            abort(403);
        }

        $offer->update($request->validated());

        return redirect()->route('offers.my')
            ->with('success', "L'offre a été mise à jour.");
    }

    /**
     * RG17: Archiver une offre (empêche les nouvelles demandes et annule les demandes en attente)
     */
    public function archive(Offer $offer)
    {
        if ($offer->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        DB::transaction(function () use ($offer) {
            $offer->status = 'archivee';
            $offer->save();

            // Annuler les demandes en attente pour cette offre (RG17)
            $pendingReservations = Reservation::whereHas('slot', fn($q) => $q->where('offer_id', $offer->id))
                ->where('status', 'pending')
                ->get();

            foreach ($pendingReservations as $res) {
                $res->status = 'cancelled';
                $res->motif_refus = "L'offre a été archivée par son auteur (RG17).";
                $res->save();
            }
        });

        return back()->with('info', "L'offre a été archivée. Les demandes en attente ont été annulées et les séances déjà acceptées sont maintenues (RG17).");
    }

    /**
     * Publier une offre
     */
    public function publish(Offer $offer)
    {
        if ($offer->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $offer->status = 'publiee';
        $offer->save();

        return back()->with('success', "L'offre est désormais publiée et visible dans le catalogue.");
    }
}
