/**
 * SkillSwap Campus — Contexte d'Authentification Global (Sanctum Bearer)
 */

import React, { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { authApi } from '../api/auth.api';
import { getStoredToken, removeStoredToken, setStoredToken } from '../api/client';
import { LoginCredentials, RegisterCredentials, User } from '../types/auth.types';

export interface AuthContextType {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    error: string | null;
    login: (credentials: LoginCredentials) => Promise<User>;
    register: (credentials: RegisterCredentials) => Promise<User>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<User | null>;
    clearError: () => void;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [user, setUser] = useState<User | null>(null);
    const [token, setTokenState] = useState<string | null>(() => getStoredToken());
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Garde anti-double-appel en React 19 / StrictMode
    const hasInitializedRef = useRef<boolean>(false);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    /**
     * Rafraîchit les informations du profil utilisateur et ses soldes en temps réel.
     */
    const refreshUser = useCallback(async (): Promise<User | null> => {
        const currentToken = getStoredToken();
        if (!currentToken) {
            setUser(null);
            setTokenState(null);
            return null;
        }

        try {
            const data = await authApi.me();
            setUser(data.user);
            setError(null);
            return data.user;
        } catch {
            // Token invalide ou expiré
            removeStoredToken();
            setUser(null);
            setTokenState(null);
            return null;
        }
    }, []);

    /**
     * Initialisation au chargement de l'application.
     */
    useEffect(() => {
        if (hasInitializedRef.current) return;
        hasInitializedRef.current = true;

        const initAuth = async () => {
            const savedToken = getStoredToken();
            if (savedToken) {
                setTokenState(savedToken);
                await refreshUser();
            } else {
                setUser(null);
                setTokenState(null);
            }
            setIsLoading(false);
        };

        initAuth();
    }, [refreshUser]);

    /**
     * Écoute des événements émis par les intercepteurs Axios (401 non autorisé, 403 compte désactivé).
     */
    useEffect(() => {
        const handleUnauthorized = () => {
            setUser(null);
            setTokenState(null);
            setError("Votre session a expiré. Veuillez vous reconnecter.");
        };

        const handleAccountDeactivated = (event: Event) => {
            const customEvent = event as CustomEvent<{ message?: string }>;
            setUser(null);
            setTokenState(null);
            setError(
                customEvent.detail?.message ||
                "Votre compte a été désactivé par un administrateur (RG18)."
            );
        };

        window.addEventListener('skillswap:auth-unauthorized', handleUnauthorized);
        window.addEventListener('skillswap:account-deactivated', handleAccountDeactivated);

        return () => {
            window.removeEventListener('skillswap:auth-unauthorized', handleUnauthorized);
            window.removeEventListener('skillswap:account-deactivated', handleAccountDeactivated);
        };
    }, []);

    /**
     * Inscription d'un nouvel étudiant.
     */
    const register = useCallback(async (credentials: RegisterCredentials): Promise<User> => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await authApi.register(credentials);
            setUser(response.user);
            setTokenState(response.token);
            setStoredToken(response.token);
            return response.user;
        } catch (err: unknown) {
            const errResponse = err as { message?: string };
            const msg = errResponse?.message || "Échec de l'inscription. Veuillez vérifier les informations saisies.";
            setError(msg);
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, []);

    /**
     * Connexion d'un utilisateur existant.
     */
    const login = useCallback(async (credentials: LoginCredentials): Promise<User> => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await authApi.login(credentials);
            setUser(response.user);
            setTokenState(response.token);
            setStoredToken(response.token);
            return response.user;
        } catch (err: unknown) {
            const errResponse = err as { message?: string };
            const msg = errResponse?.message || "Identifiants invalides.";
            setError(msg);
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, []);

    /**
     * Déconnexion complète.
     */
    const logout = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        try {
            await authApi.logout();
        } catch {
            // Même si la requête réseau échoue (ex: token déjà révoqué), on purge localement
        } finally {
            removeStoredToken();
            setUser(null);
            setTokenState(null);
            setError(null);
            setIsLoading(false);
        }
    }, []);

    const value = useMemo<AuthContextType>(() => ({
        user,
        token,
        isAuthenticated: !!user && !!token && user.statut === 'actif',
        isLoading,
        error,
        login,
        register,
        logout,
        refreshUser,
        clearError,
    }), [user, token, isLoading, error, login, register, logout, refreshUser, clearError]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

export default AuthContext;
