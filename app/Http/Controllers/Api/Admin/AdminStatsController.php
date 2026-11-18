<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminStatsController extends Controller
{
    public function __construct(
        protected AdminStatsService $adminStatsService
    ) {}

    /**
     * Retourne les indicateurs de pilotage consolidés de la plateforme.
     */
    public function index(): JsonResponse
    {
        $stats = $this->adminStatsService->getStats();

        return response()->json([
            'stats' => $stats,
        ], Response::HTTP_OK);
    }
}
