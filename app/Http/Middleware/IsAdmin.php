<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Contrôle d'accès strict au back-office administrateur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Non authentifié.',
                ], Response::HTTP_UNAUTHORIZED);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user->isAdministrateur() || !$user->isActif()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Accès refusé. Cette zone est strictement réservée aux administrateurs actifs.',
                ], Response::HTTP_FORBIDDEN);
            }
            abort(Response::HTTP_FORBIDDEN, "Accès réservé aux administrateurs de la plateforme.");
        }

        return $next($request);
    }
}

