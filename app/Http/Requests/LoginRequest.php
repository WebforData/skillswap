<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à effectuer cette requête.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour la connexion.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Messages de validation personnalisés.
     */
    public function messages(): array
    {
        return [
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "L'adresse email doit être valide.",
            'password.required' => "Le mot de passe est obligatoire.",
        ];
    }

    /**
     * Génère la clé de Rate Limiting unique combinant l'identifiant (email) normalisé et l'adresse IP.
     * Utilise rigoureusement trim() et Str::lower() pour neutraliser les espaces et la casse.
     */
    public function throttleKey(): string
    {
        $normalizedEmail = Str::lower(trim((string) $this->input('email')));

        return Str::transliterate($normalizedEmail . '|' . $this->ip());
    }

    /**
     * Vérifie si le seuil de 5 tentatives a été atteint.
     */
    public function isRateLimited(): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey(), 5);
    }

    /**
     * Nombre de secondes restantes avant de pouvoir réitérer la tentative.
     */
    public function rateLimitSeconds(): int
    {
        return RateLimiter::availableIn($this->throttleKey());
    }

    /**
     * Incrémente le compteur d'échecs (fenêtre de décroissance de 60 secondes).
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    /**
     * Réinitialise le compteur après une authentification réussie.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }
}
