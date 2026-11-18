<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreneauCreationRequest;
use App\Http\Requests\CreneauUpdateRequest;
use App\Services\CreneauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CreneauController extends Controller
{
    public function __construct(
        protected CreneauService $creneauService
    ) {}

    /**
     * Ajoute un créneau de disponibilité pour une offre (RG02, RG04, RG08).
     */
    public function store(CreneauCreationRequest $request, int $id): JsonResponse
    {
        $intervenant = $request->user();
        $creneau = $this->creneauService->ajouterCreneau($intervenant, $id, $request->validated());

        return response()->json([
            'message' => 'Le créneau a été ajouté avec succès.',
            'creneau' => $creneau,
        ], Response::HTTP_CREATED);
    }

    /**
     * Modifie un créneau horaire non attribué (RG07, RG08, RG17).
     */
    public function update(CreneauUpdateRequest $request, int $id): JsonResponse
    {
        $intervenant = $request->user();
        $creneau = $this->creneauService->modifierCreneau($intervenant, $id, $request->validated());

        return response()->json([
            'message' => 'Le créneau a été mis à jour avec succès.',
            'creneau' => $creneau,
        ], Response::HTTP_OK);
    }

    /**
     * Supprime un créneau horaire non attribué (RG07, RG17).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $intervenant = $request->user();
        $this->creneauService->supprimerCreneau($intervenant, $id);

        return response()->json([
            'message' => 'Le créneau a été supprimé avec succès.',
        ], Response::HTTP_OK);
    }
}
