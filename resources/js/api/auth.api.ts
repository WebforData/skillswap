/**
 * SkillSwap Campus — Module API Authentification (RG01, RG18)
 */

import apiClient, { setStoredToken, removeStoredToken } from './client';
import { AuthResponse, LoginCredentials, RegisterCredentials, User } from '../types/auth.types';

export const authApi = {
    /**
     * Inscription d'un nouvel étudiant et attribution automatique des 2 crédits de bienvenue (RG01).
     * POST /api/auth/register
     */
    async register(credentials: RegisterCredentials): Promise<AuthResponse> {
        const response = await apiClient.post<AuthResponse>('/auth/register', credentials);
        if (response.data.token) {
            setStoredToken(response.data.token);
        }
        return response.data;
    },

    /**
     * Connexion d'un utilisateur existant (étudiant ou administrateur).
     * POST /api/auth/login
     */
    async login(credentials: LoginCredentials): Promise<AuthResponse> {
        const response = await apiClient.post<AuthResponse>('/auth/login', credentials);
        if (response.data.token) {
            setStoredToken(response.data.token);
        }
        return response.data;
    },

    /**
     * Déconnexion et révocation exclusive du token courant côté serveur.
     * POST /api/auth/logout
     */
    async logout(): Promise<{ message: string }> {
        try {
            const response = await apiClient.post<{ message: string }>('/auth/logout');
            return response.data;
        } finally {
            // Dans tous les cas, on purge localement le token
            removeStoredToken();
        }
    },

    /**
     * Récupère le profil de l'utilisateur authentifié et son solde temps réel.
     * GET /api/auth/me
     */
    async me(): Promise<{ user: User }> {
        const response = await apiClient.get<{ user: User }>('/auth/me');
        return response.data;
    },
};

export default authApi;
