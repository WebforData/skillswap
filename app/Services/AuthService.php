<?php

namespace App\Services;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\OperationFinanciereException;
use App\Models\Portefeuille;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * Inscription d'un nouvel étudiant (RG01).
     *
     * Opération strictement atomique : si une étape échoue (ex: création portefeuille ou attribution crédits),
     * la transaction SQL est intégralement annulée et aucune trace n'est persistée en base.
     *
     * @param  array  $data
     * @return array
     *
     * @throws ValidationException
     * @throws OperationFinanciereException
     * @throws QueryException
     */
    public function register(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $email = strtolower(trim($data['email']));

                // 1. Vérification applicative d'unicité de l'email (RG01)
                if (User::where('email', $email)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ["Cette adresse électronique est déjà associée à un compte existant (RG01)."],
                    ]);
                }

                // 2. Création de l'utilisateur avec hachage sécurisé du mot de passe
                $user = new User();
                $user->nom = trim($data['nom']);
                $user->prenom = trim($data['prenom']);
                $user->email = $email;
                $user->password = Hash::make($data['password']);
                $user->filiere = isset($data['filiere']) ? trim($data['filiere']) : null;
                $user->competences_proposees = $data['competences_proposees'] ?? null;
                $user->competences_recherchees = $data['competences_recherchees'] ?? null;

                // SÉCURITÉ RG01 & RG18 : Le rôle et le statut sont assignés explicitement par le backend.
                // Ils ne peuvent jamais être injectés ou modifiés par l'utilisateur lors de l'inscription.
                $user->role = 'etudiant';
                $user->statut = 'actif';
                $user->save();

                // 3. Création initiale du portefeuille à 0 crédits
                $portefeuille = new Portefeuille();
                $portefeuille->etudiant_id = $user->id;
                $portefeuille->solde_disponible = 0;
                $portefeuille->credits_bloques = 0;
                $portefeuille->save();

                // 4. Attribution des 2 crédits de bienvenue via CreditService (RG01)
                $this->creditService->attribuerCreditsBienvenue($user);

                // 5. Génération du token Sanctum personnel
                $token = $user->createToken('auth_token')->plainTextToken;

                return [
                    'user' => $this->formatUserResponse($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ];
            });
        } catch (QueryException $e) {
            // Seule la violation de contrainte UNIQUE ciblant users.email est convertie en ValidationException (HTTP 422)
            if ($this->isDuplicateEmailException($e)) {
                throw ValidationException::withMessages([
                    'email' => ["Cette adresse électronique est déjà associée à un compte existant (RG01)."],
                ]);
            }

            // Les autres erreurs SQL (clé étrangère, NOT NULL, trigger, schéma) remontent normalement
            throw $e;
        }
    }

    /**
     * Identifie précisément si l'exception SQL correspond à une violation de la contrainte UNIQUE sur users.email.
     * Vérifie obligatoirement le code MySQL 1062 et la contrainte 'users_email_unique', ou le message SQLite exact.
     * Toute autre erreur SQL remonte normalement comme QueryException.
     *
     * @param  QueryException  $e
     * @return bool
     */
    public function isDuplicateEmailException(QueryException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? null;
        $message = $e->getMessage();

        // 1. MySQL / MariaDB : Code d'erreur 1062 (ER_DUP_ENTRY) avec contrainte stricte 'users_email_unique'
        if ($errorCode === 1062 && str_contains($message, 'users_email_unique')) {
            return true;
        }

        // 2. SQLite : Violation de contrainte UNIQUE ciblant strictement 'users.email'
        if (str_contains($message, 'UNIQUE constraint failed: users.email')) {
            return true;
        }

        return false;
    }

    /**
     * Connexion d'un utilisateur existant.
     *
     * Sécurité : ne révèle jamais si l'email existe en cas d'échec et refuse
     * catégoriquement l'accès aux comptes désactivés (RG18).
     *
     * @param  array  $credentials
     * @return array
     *
     * @throws AuthenticationException
     * @throws CompteDesactiveException
     */
    public function login(array $credentials): array
    {
        $email = strtolower(trim($credentials['email']));

        $user = User::where('email', $email)->first();

        // Sécurité anti-énumération : message générique uniforme
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw new AuthenticationException("Les identifiants fournis sont incorrects.");
        }

        // RG18 : Refus formel de connexion pour les comptes désactivés
        if (!$user->isActif()) {
            throw new CompteDesactiveException("Votre compte a été désactivé par un administrateur (RG18). Veuillez contacter le support.");
        }

        // Génération du token Sanctum personnel
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $this->formatUserResponse($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Déconnexion de l'utilisateur connecté : révoque STRICTEMENT son token actuel.
     * Les autres tokens de l'utilisateur (autres sessions, mobiles, navigateurs) restent valides.
     *
     * @param  User  $user
     * @return bool
     */
    public function logout(User $user): bool
    {
        $accessToken = $user->currentAccessToken();

        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
            return true;
        }

        return false;
    }

    /**
     * Récupère le profil de l'utilisateur connecté avec son solde de crédits en temps réel.
     *
     * @param  User  $user
     * @return array
     */
    public function me(User $user): array
    {
        return [
            'user' => $this->formatUserResponse($user),
        ];
    }

    /**
     * Formate les informations publiques et financières de l'utilisateur.
     * Ne renvoie jamais le mot de passe, tokens ni attributs sensibles.
     * N'utilise JAMAIS de solde fictif : propage l'exception si le portefeuille est introuvable.
     *
     * @param  User  $user
     * @return array
     */
    public function formatUserResponse(User $user): array
    {
        // Récupération stricte du solde réel via CreditService (aucun solde fictif)
        $solde = $this->creditService->getSolde($user);

        return [
            'id' => $user->id,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'email' => $user->email,
            'role' => $user->role,
            'statut' => $user->statut,
            'filiere' => $user->filiere,
            'competences_proposees' => $user->competences_proposees,
            'competences_recherchees' => $user->competences_recherchees,
            'portefeuille' => $solde,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
