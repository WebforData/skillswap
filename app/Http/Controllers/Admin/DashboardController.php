<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\CreditTransaction;
use App\Models\Dispute;
use App\Models\Category;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard Administrateur avec les 6 indicateurs demandés par le cahier des charges :
     * 1. Comptes actifs
     * 2. Offres publiées
     * 3. Séances acceptées
     * 4. Séances réalisées
     * 5. Crédits transférés
     * 6. Litiges ouverts
     */
    public function index()
    {
        $comptesActifs = User::where('role', 'etudiant')->where('is_active', true)->count();
        $comptesDesactives = User::where('role', 'etudiant')->where('is_active', false)->count();
        $offresPubliees = Offer::where('status', 'publiee')->where('is_masked', false)->count();
        $offresMasquees = Offer::where('is_masked', true)->count();

        $seancesAcceptees = Reservation::where('status', 'accepted')->count();
        $seancesRealisees = Reservation::where('status', 'completed')->count();

        // Total des crédits transférés
        $creditsTransferes = abs(CreditTransaction::whereIn('type', ['transfert_credit', 'litige_transfert'])->sum('montant'));

        $litigesOuverts = Dispute::where('resolution', 'en_cours')->count();

        // Données pour Chart.js : Répartition des offres par catégorie
        $categoriesStats = Category::withCount('offers')->get();
        $chartLabels = $categoriesStats->pluck('nom');
        $chartData = $categoriesStats->pluck('offers_count');

        // Dernières réservations et litiges récents
        $recentDisputes = Dispute::with(['reservation.slot.offer', 'openedBy'])->latest()->take(5)->get();
        $recentUsers = User::where('role', 'etudiant')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'comptesActifs',
            'comptesDesactives',
            'offresPubliees',
            'offresMasquees',
            'seancesAcceptees',
            'seancesRealisees',
            'creditsTransferes',
            'litigesOuverts',
            'chartLabels',
            'chartData',
            'recentDisputes',
            'recentUsers'
        ));
    }
}
