<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminOffreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOffreController extends Controller
{
    public function __construct(
        protected AdminOffreService $adminOffreService
    ) {}

    /**
     * Liste paginée des offres pour l'administration.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginator = $this->adminOffreService->listerOffres($request->all(), $perPage);

        return response()->json([
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'offres' => $paginator->items(),
        ], Response::HTTP_OK);
    }

    /**
     * Affiche les détails d'une offre pour modération.
     */
    public function show(int $id): JsonResponse
    {
        $offre = $this->adminOffreService->getOffre($id);

        return response()->json([
            'offre' => $offre,
        ], Response::HTTP_OK);
    }

    /**
     * Modère et archive une offre (RG17).
     */
    public function archiver(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $offre = $this->adminOffreService->archiverOffre($admin, $id);

        return response()->json([
            'message' => 'L\'offre a été archivée avec succès. Les demandes en attente ont été annulées et les réservations engagées préservées (RG17).',
            'offre' => $offre,
        ], Response::HTTP_OK);
    }
}
