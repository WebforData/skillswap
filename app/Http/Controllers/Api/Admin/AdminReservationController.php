<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Reservation;
use App\Services\LitigeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReservationController extends Controller
{
    public function index(Request $request)
    {
        $items = Reservation::where('statut', 'acceptee')->where(function ($q) {
            $q->whereHas('beneficiaire', fn ($u) => $u->where('statut', 'desactive'))
                ->orWhereHas('creneau.offre.intervenant', fn ($u) => $u->where('statut', 'desactive'));
        })->with(['creneau.offre.intervenant', 'beneficiaire'])->latest()->paginate(15);
        $items->setCollection($items->getCollection()->map(fn ($r) => (new ReservationResource($r))->resolve($request)));

        return response()->json($items);
    }

    public function settle(Request $request, int $id, LitigeService $service)
    {
        $data = $request->validate(['decision' => 'required|in:transfert_intervenant,restitution_beneficiaire', 'motif' => 'required|string|min:5|max:2000']);

        return DB::transaction(function () use ($request, $id, $service, $data) {
            $candidate = Reservation::findOrFail($id);
            Creneau::whereKey($candidate->creneau_id)->lockForUpdate()->firstOrFail();
            $r = Reservation::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($r->statut === 'acceptee', 409, 'Cette séance est déjà traitée.');
            abort_unless(! $r->beneficiaire->isActif() || ! $r->creneau->offre->intervenant->isActif(), 422, 'Cette séance ne concerne aucun compte désactivé.');
            $end = Carbon::parse($r->creneau->date->format('Y-m-d').' '.$r->creneau->heure_fin);
            abort_if($data['decision'] === 'transfert_intervenant' && now()->lt($end), 422, 'Le transfert exige une séance terminée.');
            $r->statut = 'en_litige';
            $r->save();
            $litige = new Litige;
            $litige->forceFill(['reservation_id' => $id, 'declarant_id' => $request->user()->id, 'description' => 'Examen administratif après désactivation : '.$data['motif'], 'statut' => 'ouvert'])->save();
            $service->arbitrerLitige($request->user(), $litige->id, $data['decision'], $data['motif']);

            return response()->json(['message' => 'Séance réglée définitivement.']);
        }, 3);
    }
}
