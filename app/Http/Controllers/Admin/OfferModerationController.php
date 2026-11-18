<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferModerationController extends Controller
{
    public function index(Request $request)
    {
        $query = Offer::with(['user', 'category', 'slots']);

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('titre', 'LIKE', "%{$q}%")
                    ->orWhere('competence', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('masked')) {
            $query->where('is_masked', $request->get('masked') === '1');
        }

        $offers = $query->latest()->paginate(15)->withQueryString();

        return view('admin.offers.index', compact('offers'));
    }

    /**
     * RG17: Masquer ou démasquer une offre pour non-respect des règles
     */
    public function toggleMask(Offer $offer)
    {
        DB::transaction(function () use ($offer) {
            $offer->is_masked = !$offer->is_masked;
            $offer->save();

            // Si l'offre est masquée, annuler automatiquement les demandes en attente (RG17)
            if ($offer->is_masked) {
                $pendingRequests = Reservation::whereHas('slot', fn($q) => $q->where('offer_id', $offer->id))
                    ->where('status', 'pending')
                    ->get();

                foreach ($pendingRequests as $req) {
                    $req->status = 'cancelled';
                    $req->motif_refus = "Offre masquée par la modération (RG17).";
                    $req->save();
                }

                Notification::create([
                    'user_id' => $offer->user_id,
                    'titre' => 'Offre masquée par la modération',
                    'message' => "Votre offre '{$offer->titre}' a été masquée car elle ne respectait pas les critères universitaires. Les demandes en attente ont été annulées (RG17).",
                    'link' => route('offers.my'),
                ]);
            }
        });

        $status = $offer->is_masked ? 'masquée du catalogue' : 'rendue à nouveau visible';
        return back()->with('info', "L'offre a été {$status} (RG17).");
    }
}
