<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreneauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function __construct(
        protected CreneauService $creneauService
    ) {}

    /**
     * Récupère le planning et calendrier consolidé de l'étudiant (RG08, RG19).
     */
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        $planning = $this->creneauService->getPlanning($utilisateur, $request->all());

        return response()->json([
            'total' => count($planning),
            'planning' => $planning,
        ]);
    }
}
