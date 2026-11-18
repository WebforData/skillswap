<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CompteDesactiveException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Inscription d'un nouvel étudiant et attribution des 2 crédits de bienvenue (RG01).
     *
     * @param  RegisterRequest  $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Inscription réussie. Vos 2 crédits de bienvenue ont été attribués.',
            'user' => $result['user'],
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], Response::HTTP_CREATED);
    }

    /**
     * Connexion d'un utilisateur existant avec protection contre les attaques par force brute (Rate Limiting).
     *
     * @param  LoginRequest  $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // 1. Contrôle anti-force brute par IP et identifiant (5 tentatives maximum)
        if ($request->isRateLimited()) {
            $seconds = $request->rateLimitSeconds();

            return response()->json([
                'message' => "Trop de tentatives de connexion. Veuillez réessayer dans {$seconds} secondes.",
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $result = $this->authService->login($request->validated());

            // 2. Réinitialisation des tentatives après connexion réussie
            $request->clearRateLimiter();

            return response()->json([
                'message' => 'Connexion réussie.',
                'user' => $result['user'],
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ], Response::HTTP_OK);
        } catch (AuthenticationException $e) {
            // 3. Incrémenter le compteur d'échecs en cas d'identifiants incorrects
            $request->hitRateLimiter();

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        } catch (CompteDesactiveException $e) {
            // 4. Incrémenter le compteur d'échecs en cas de compte désactivé
            $request->hitRateLimiter();

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Déconnexion de l'utilisateur connecté : révocation exclusive du token Sanctum actuel.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Déconnexion réussie. Le token a été révoqué.',
        ], Response::HTTP_OK);
    }

    /**
     * Profil de l'utilisateur authentifié avec ses soldes de crédits en temps réel.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $result = $this->authService->me($request->user());

        return response()->json($result, Response::HTTP_OK);
    }
}
