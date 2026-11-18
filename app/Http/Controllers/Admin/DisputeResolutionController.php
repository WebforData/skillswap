<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Notification;
use App\Services\CreditWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class DisputeResolutionController extends Controller
{
    protected CreditWalletService $walletService;

    public function __construct(CreditWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function index()
    {
        $disputes = Dispute::with(['reservation.slot.offer.user', 'reservation.beneficiary', 'openedBy'])
            ->latest()
            ->paginate(15);

        return view('admin.disputes.index', compact('disputes'));
    }

    /**
     * RG13 & RG14: Arbitrer définitivement un litige
     */
    public function resolve(Request $request, Dispute $dispute)
    {
        $request->validate([
            'resolution' => ['required', 'in:transfert_formateur,restitution_beneficiaire'],
            'decision_admin' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'decision_admin.required' => "Vous devez obligatoirement motiver votre décision arbitrale (RG13).",
        ]);

        // RG14: Un règlement est définitif, aucun second règlement n'est autorisé
        if ($dispute->isResolved()) {
            return back()->with('error', "Ce litige a déjà été définitivement clôturé (RG14). Aucun second règlement n'est permis.");
        }

        try {
            DB::transaction(function () use ($dispute, $request) {
                // Application de la décision financière (RG13 & RG16)
                $this->walletService->resolveDispute($dispute->reservation, $request->resolution, $request->decision_admin);

                $dispute->resolution = $request->resolution;
                $dispute->decision_admin = $request->decision_admin;
                $dispute->resolved_at = now();
                $dispute->save();

                $beneficiary = $dispute->reservation->beneficiary;
                $formateur = $dispute->reservation->slot->offer->user;

                // Notifier les deux parties
                $decisionText = ($request->resolution === 'transfert_formateur') 
                    ? "Transfert intégral validé en faveur du formateur." 
                    : "Restitution intégrale des crédits en faveur du bénéficiaire.";

                Notification::create([
                    'user_id' => $beneficiary->id,
                    'titre' => 'Litige clôturé par l\'administration',
                    'message' => "Décision concernant la séance '{$dispute->reservation->slot->offer->titre}' : {$decisionText}. Motif : {$request->decision_admin}",
                    'link' => route('disputes.my'),
                ]);

                Notification::create([
                    'user_id' => $formateur->id,
                    'titre' => 'Litige clôturé par l\'administration',
                    'message' => "Décision concernant la séance '{$dispute->reservation->slot->offer->titre}' : {$decisionText}. Motif : {$request->decision_admin}",
                    'link' => route('disputes.my'),
                ]);
            });

            return back()->with('success', "Le litige a été tranché et clôturé de manière définitive. Les crédits ont été régularisés en BDD (RG13, RG14).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
