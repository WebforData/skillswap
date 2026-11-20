<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EvaluationRequest;
use App\Http\Resources\EvaluationResource;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Dépose une évaluation pour une séance réalisée (RG15).
     */
    public function store(EvaluationRequest $request): JsonResponse
    {
        $auteur = $request->user();

        $evaluation = $this->evaluationService->evaluerReservation(
            $auteur,
            $request->validated('reservation_id'),
            $request->validated('note'),
            $request->validated('commentaire')
        );

        return response()->json([
            'message' => 'Votre évaluation a été enregistrée avec succès (RG15).',
            'evaluation' => [
                'id' => $evaluation->id,
                'reservation_id' => $evaluation->reservation_id,
                'auteur_id' => $evaluation->auteur_id,
                'cible_id' => $evaluation->cible_id,
                'note' => $evaluation->note,
                'commentaire' => $evaluation->commentaire,
                'created_at' => $evaluation->created_at?->toIso8601String(),
                'auteur' => [
                    'id' => $evaluation->auteur?->id,
                    'nom' => $evaluation->auteur?->nom,
                    'prenom' => $evaluation->auteur?->prenom,
                ],
                'cible' => [
                    'id' => $evaluation->cible?->id,
                    'nom' => $evaluation->cible?->nom,
                    'prenom' => $evaluation->cible?->prenom,
                ],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Liste les évaluations reçues par l'étudiant connecté.
     */
    public function indexRecues(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $evaluations = $this->evaluationService->getEvaluationsRecues($user, $perPage);

        return response()->json($evaluations);
    }

    /**
     * Liste les évaluations publiées par l'étudiant connecté.
     */
    public function indexDonnees(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $evaluations = $this->evaluationService->getEvaluationsDonnees($user, $perPage);

        return response()->json($evaluations);
    }

    /**
     * Statistiques d'évaluation d'un étudiant (note moyenne et total).
     */
    public function publicReviews(int $id): JsonResponse
    {
        $items = $this->evaluationService->getEvaluationsRecues(User::findOrFail($id), 10);
        $items->setCollection($items->getCollection()->map(fn ($evaluation) => (new EvaluationResource($evaluation))->resolve(request())));

        return response()->json($items);
    }

    public function getStats(int $userId): JsonResponse
    {
        $stats = $this->evaluationService->getStatistiquesEtudiant($userId);

        return response()->json($stats);
    }
}
