/**
 * SkillSwap Campus — Client Axios Sécurisé (Laravel Sanctum Bearer Token)
 */

import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { ApiErrorResponse } from '../types/api.types';

const TOKEN_STORAGE_KEY = 'skillswap_auth_token';

// Stockage en mémoire prioritaire
let inMemoryToken: string | null = null;

// Initialisation au chargement
try {
    inMemoryToken = localStorage.getItem(TOKEN_STORAGE_KEY);
} catch {
    inMemoryToken = null;
}

/**
 * Récupère le token d'authentification courant.
 */
export const getStoredToken = (): string | null => {
    return inMemoryToken;
};

/**
 * Définit et persiste le token d'authentification.
 */
export const setStoredToken = (token: string | null): void => {
    inMemoryToken = token;
    try {
        if (token) {
            localStorage.setItem(TOKEN_STORAGE_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_STORAGE_KEY);
        }
    } catch {
        // En cas d'environnement restreint (mode privé extrême)
    }
};

/**
 * Supprime le token d'authentification.
 */
export const removeStoredToken = (): void => {
    setStoredToken(null);
};

/**
 * Instance Axios configurée pour l'API REST SkillSwap Campus.
 */
export const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api',
    timeout: 15000,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * Intercepteur de requête : injection automatique du token Bearer Sanctum.
 */
apiClient.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        const token = getStoredToken();
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

/**
 * Intercepteur de réponse : gestion centralisée des codes HTTP 401, 403 (RG18), 429 et 500.
 */
apiClient.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiErrorResponse>) => {
        if (!error.response) {
            // Erreur réseau ou serveur inaccessible
            error.message = "Impossible de joindre le serveur. Veuillez vérifier votre connexion.";
            return Promise.reject(error);
        }

        const status = error.response.status;
        const data = error.response.data;

        switch (status) {
            case 401: {
                // Token expiré, invalide ou révoqué
                removeStoredToken();
                window.dispatchEvent(
                    new CustomEvent('skillswap:auth-unauthorized', {
                        detail: { message: data?.message || "Session expirée. Veuillez vous reconnecter." },
                    })
                );
                break;
            }

            case 403: {
                // RG18 : Déconnexion UNIQUEMENT si le compte a été désactivé
                const isDeactivated =
                    data?.error === 'CompteDesactiveException' ||
                    (typeof data?.message === 'string' &&
                        data.message.toLowerCase().includes('désactivé'));

                if (isDeactivated) {
                    removeStoredToken();
                    window.dispatchEvent(
                        new CustomEvent('skillswap:account-deactivated', {
                            detail: {
                                message:
                                    data?.message ||
                                    "Votre compte a été désactivé par un administrateur (RG18).",
                            },
                        })
                    );
                }
                // Si 403 ordinaire (action interdite pour son rôle), on ne déconnecte PAS l'utilisateur !
                break;
            }

            case 429: {
                // Rate-limiting / anti-brute force
                const retryAfter = error.response.headers['retry-after'];
                const message = retryAfter
                    ? `Trop de requêtes. Veuillez patienter ${retryAfter} secondes.`
                    : (data?.message || "Trop de requêtes. Veuillez patienter un instant.");

                window.dispatchEvent(
                    new CustomEvent('skillswap:rate-limited', {
                        detail: { message, retryAfter },
                    })
                );
                break;
            }

            case 500: {
                // Erreur interne serveur : message générique sans exposer de stacktrace
                error.message = "Une erreur interne est survenue sur le serveur. Veuillez réessayer plus tard.";
                error.response.data = { message: error.message, statusCode: 500 };
                return Promise.reject(error);
            }
        }

        // Keep the Axios response so forms can display validation and business errors.
        return Promise.reject(error);
    }
);

export default apiClient;
