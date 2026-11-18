<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminUserService $adminUserService
    ) {}

    /**
     * Liste paginée des utilisateurs pour l'administration.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginator = $this->adminUserService->listerUtilisateurs($request->all(), $perPage);

        return response()->json([
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'users' => $paginator->items(),
        ], Response::HTTP_OK);
    }

    /**
     * Consulte le profil détaillé d'un utilisateur.
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->adminUserService->getUtilisateur($id);

        return response()->json([
            'user' => $user,
        ], Response::HTTP_OK);
    }

    /**
     * Désactive un compte utilisateur (RG18).
     */
    public function desactiver(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $user = $this->adminUserService->desactiverCompte($admin, $id);

        return response()->json([
            'message' => "Le compte de {$user->prenom} {$user->nom} a été désactivé (RG18). Les demandes en attente ont été annulées et les crédits bloqués préservés.",
            'user' => $user,
        ], Response::HTTP_OK);
    }

    /**
     * Réactive un compte utilisateur.
     */
    public function activer(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $user = $this->adminUserService->activerCompte($admin, $id);

        return response()->json([
            'message' => "Le compte de {$user->prenom} {$user->nom} a été réactivé avec succès.",
            'user' => $user,
        ], Response::HTTP_OK);
    }
}
