<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveUser
{
    /**
     * RG18: Un compte désactivé ne peut plus se connecter ni recevoir de nouvelles demandes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->isActif()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Votre compte est désactivé (RG18). Aucune opération n\'est autorisée.',
                    'error' => 'CompteDesactiveException',
                ], Response::HTTP_FORBIDDEN);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Votre compte a été désactivé par un administrateur (RG18). Veuillez contacter le support.');
        }

        return $next($request);
    }
}
