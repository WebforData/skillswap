<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'etudiant');

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('nom', 'LIKE', "%{$q}%")
                    ->orWhere('prenom', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('filiere', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = ($request->get('status') === 'actif');
            $query->where('is_active', $isActive);
        }

        $users = $query->withCount(['offers', 'reservationsAsBeneficiary'])->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * RG18: Basculer l'état actif/désactivé d'un compte
     */
    public function toggleStatus(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', "Impossible de désactiver un compte administrateur.");
        }

        DB::transaction(function () use ($user) {
            $user->is_active = !$user->is_active;
            $user->save();

            // Si le compte est désactivé, annuler les demandes en attente (RG18)
            if (!$user->is_active) {
                // Demandes envoyées par l'étudiant en attente
                Reservation::where('beneficiary_id', $user->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'motif_refus' => "Compte demandeur désactivé par l'administration (RG18)."
                    ]);

                // Demandes reçues sur les offres de l'étudiant en attente
                Reservation::whereHas('slot.offer', fn($q) => $q->where('user_id', $user->id))
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'motif_refus' => "Compte intervenant désactivé par l'administration (RG18)."
                    ]);
            }
        });

        $message = $user->is_active 
            ? "Le compte de {$user->nom_complet} a été réactivé." 
            : "Le compte de {$user->nom_complet} a été désactivé (RG18). Ses demandes en attente ont été annulées et aucun crédit bloqué n'a été perdu.";

        return back()->with('info', $message);
    }
}
