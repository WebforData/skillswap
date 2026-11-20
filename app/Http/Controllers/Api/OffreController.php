<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OffreCreationRequest;
use App\Http\Requests\OffreUpdateRequest;
use App\Http\Resources\CreneauResource;
use App\Http\Resources\OffreResource;
use App\Services\OffreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OffreController extends Controller
{
    public function __construct(
        protected OffreService $offreService
    ) {}

    /**
     * Catalogue public : recherche et filtres combinés (RG04).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 12);
        $offres = $this->offreService->rechercherOffres($request->all(), $perPage);

        return response()->json($offres);
    }

    /**
     * Mes offres : liste des offres proposées par l'intervenant connecté.
     */
    public function mesOffres(Request $request): JsonResponse
    {
        $intervenant = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $offres = $this->offreService->getMesOffres($intervenant, $perPage);

        $offres->setCollection($offres->getCollection()->map(fn ($offre) => (new OffreResource($offre))->resolve($request)));

        return response()->json($offres);
    }

    /**
     * Consultation d'une offre détaillée avec ses créneaux disponibles.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $visiteur = $request->user('sanctum');
        $offre = $this->offreService->getOffre($id, $visiteur);
        $request->setUserResolver(fn () => $visiteur);
        $data = $offre->toArray();
        $data['creneaux'] = $offre->creneaux->map(fn ($slot) => (new CreneauResource($slot))->resolve($request));

        return response()->json(['offre' => $data]);
    }

    /**
     * Création d'une nouvelle offre d'échange (RG02, RG04).
     */
    public function store(OffreCreationRequest $request): JsonResponse
    {
        $intervenant = $request->user();
        $offre = $this->offreService->creerOffre($intervenant, $request->validated());

        return response()->json([
            'message' => "L'offre '{$offre->titre}' a été créée avec succès.",
            'offre' => $offre,
        ], Response::HTTP_CREATED);
    }

    /**
     * Modification d'une offre existante (propriétaire uniquement).
     */
    public function update(OffreUpdateRequest $request, int $id): JsonResponse
    {
        $intervenant = $request->user();
        $offre = $this->offreService->modifierOffre($intervenant, $id, $request->validated());

        return response()->json([
            'message' => "L'offre a été mise à jour avec succès.",
            'offre' => $offre,
        ]);
    }

    /**
     * Publication d'une offre (la rend visible au catalogue).
     */
    public function publier(Request $request, int $id): JsonResponse
    {
        $intervenant = $request->user();
        $offre = $this->offreService->publierOffre($intervenant, $id);

        return response()->json([
            'message' => "L'offre est désormais publiée et visible aux étudiants (RG04).",
            'offre' => $offre,
        ]);
    }

    /**
     * Repasser une offre en brouillon.
     */
    public function brouillon(Request $request, int $id): JsonResponse
    {
        $intervenant = $request->user();
        $offre = $this->offreService->passerEnBrouillon($intervenant, $id);

        return response()->json([
            'message' => "L'offre a été repassée en brouillon.",
            'offre' => $offre,
        ]);
    }

    /**
     * Archivage d'une offre (RG17).
     */
    public function archiver(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();
        $offre = $this->offreService->archiverOffre($utilisateur, $id);

        return response()->json([
            'message' => "L'offre a été archivée avec succès. Les demandes en attente ont été annulées (RG17).",
            'offre' => $offre,
        ]);
    }
}
