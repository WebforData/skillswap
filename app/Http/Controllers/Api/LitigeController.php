<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LitigeArbitrageRequest;
use App\Http\Resources\LitigeResource;
use App\Models\Litige;
use App\Models\User;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LitigeController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = Litige::whereHas('reservation', fn ($q) => $q->where('beneficiaire_id', $user->id)
            ->orWhereHas('creneau.offre', fn ($o) => $o->where('etudiant_id', $user->id)))
            ->with(['reservation.creneau.offre.intervenant', 'reservation.beneficiaire', 'declarant', 'administrateur'])->latest()->get();

        return response()->json(['litiges' => LitigeResource::collection($items)]);
    }

    public function statement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $id, $data) {
            $litige = Litige::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($litige->reservation->isParticipant($request->user()), 403);
            abort_unless($litige->statut === 'ouvert', 409, 'Ce litige est déjà réglé.');
            DB::table('litige_statements')->insert(['litige_id' => $id, 'user_id' => $request->user()->id, 'message' => $data['message'], 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['message' => 'Votre témoignage a été ajouté.'], 201);
        });
    }

    public function __construct(
        protected LitigeService $litigeService
    ) {}

    /**
     * Liste paginée des litiges pour l'administration.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdministrateur()) {
            return response()->json([
                'message' => 'Accès interdit. Seuls les administrateurs peuvent consulter la liste des litiges (RG13).',
            ], Response::HTTP_FORBIDDEN);
        }

        $query = Litige::with([
            'reservation.creneau.offre.intervenant',
            'reservation.beneficiaire',
            'declarant',
            'administrateur',
        ])->latest();

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'litiges' => LitigeResource::collection($paginator->items()),
        ], Response::HTTP_OK);
    }

    /**
     * Consulte un litige en détail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $litige = Litige::with([
            'reservation.creneau.offre.intervenant',
            'reservation.beneficiaire',
            'declarant',
            'administrateur',
        ])->findOrFail($id);

        if (! $user || (! $user->isAdministrateur() && ! $litige->reservation?->isParticipant($user))) {
            return response()->json([
                'message' => 'Accès interdit. Vous n\'êtes pas autorisé à consulter ce litige (RG19).',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'litige' => new LitigeResource($litige),
        ], Response::HTTP_OK);
    }

    /**
     * Arbitre définitivement un dossier de litige (RG13, RG14).
     * Endpoint réservé exclusivement aux administrateurs actifs.
     */
    public function arbitrer(LitigeArbitrageRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $litige = $this->litigeService->arbitrerLitige(
            $user,
            $id,
            $request->validated('decision'),
            $request->validated('motif')
        );

        $actionMessage = $litige->decision === 'transfert_intervenant'
            ? 'Litige résolu en faveur de l\'intervenant : les crédits ont été transférés.'
            : 'Litige résolu en faveur du bénéficiaire : les crédits ont été intégralement restitués.';

        return response()->json([
            'message' => $actionMessage,
            'litige' => [
                'id' => $litige->id,
                'reservation_id' => $litige->reservation_id,
                'declarant_id' => $litige->declarant_id,
                'admin_id' => $litige->admin_id,
                'statut' => $litige->statut,
                'decision' => $litige->decision,
                'motif_decision' => $litige->motif_decision,
                'date_resolution' => $litige->date_resolution?->toIso8601String(),
                'reservation' => $litige->reservation ? [
                    'id' => $litige->reservation->id,
                    'statut' => $litige->reservation->statut,
                ] : null,
                'administrateur' => $litige->administrateur ? [
                    'id' => $litige->administrateur->id,
                    'nom' => $litige->administrateur->nom,
                    'prenom' => $litige->administrateur->prenom,
                ] : null,
            ],
        ], Response::HTTP_OK);
    }
}
