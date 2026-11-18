<?php

namespace App\Services;

use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminStatsService
{
    /**
     * Calcule l'ensemble des indicateurs de la plateforme de manière optimisée (zéro N+1).
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'utilisateurs' => [
                'total' => User::count(),
                'etudiants' => User::where('role', 'etudiant')->count(),
                'administrateurs' => User::where('role', 'administrateur')->count(),
                'actifs' => User::where('statut', 'actif')->count(),
                'desactives' => User::where('statut', 'desactive')->count(),
            ],
            'offres' => [
                'total' => Offre::count(),
                'publiees' => Offre::where('statut', 'publiee')->count(),
                'brouillons' => Offre::where('statut', 'brouillon')->count(),
                'archivees' => Offre::where('statut', 'archivee')->count(),
            ],
            'reservations' => [
                'total' => Reservation::count(),
                'en_attente' => Reservation::where('statut', 'en_attente')->count(),
                'acceptees' => Reservation::where('statut', 'acceptee')->count(),
                'realisees' => Reservation::where('statut', 'realisee')->count(),
                'annulees' => Reservation::where('statut', 'annulee')->count(),
                'en_litige' => Reservation::where('statut', 'en_litige')->count(),
                'refusees' => Reservation::where('statut', 'refusee')->count(),
            ],
            'activite_financiere' => [
                'seances_realisees' => Reservation::where('statut', 'realisee')->count(),
                'credits_transferes' => (int) MouvementCredit::where('type', 'transfert_gain')->where('quantite', '>', 0)->sum('quantite'),
                'credits_sequestres' => (int) Portefeuille::sum('credits_bloques'),
                'litiges_ouverts' => Litige::where('statut', 'ouvert')->count(),
                'litiges_resolus' => Litige::where('statut', 'resolu')->count(),
            ],
            'evolution_mensuelle' => $this->getEvolutionMensuelle(),
        ];
    }

    /**
     * Évolution des réservations par mois sur les 6 derniers mois (compatible MySQL et SQLite).
     *
     * @return array
     */
    protected function getEvolutionMensuelle(): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $dateFormat = $isSqlite ? "strftime('%Y-%m', date_demande)" : "DATE_FORMAT(date_demande, '%Y-%m')";

        return Reservation::select(DB::raw("{$dateFormat} as mois, COUNT(*) as total"))
            ->where('date_demande', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->orderBy('mois', 'asc')
            ->get()
            ->toArray();
    }
}
