/**
 * Tests Unitaires & Intégration Frontend — Page de Connexion (LoginPage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { LoginPage } from '../../pages/auth/LoginPage';
import { AuthContext, AuthContextType } from '../../contexts/AuthContext';
import { NotificationContext, NotificationContextType } from '../../contexts/NotificationContext';
import axios from 'axios';

// Mock Notification Context
const mockSuccess = vi.fn();
const mockError = vi.fn();
const mockWarning = vi.fn();
const mockInfo = vi.fn();

const mockNotificationContext: NotificationContextType = {
    showNotification: vi.fn(),
    removeNotification: vi.fn(),
    success: mockSuccess,
    error: mockError,
    warning: mockWarning,
    info: mockInfo,
};

const createMockAuthContext = (overrides: Partial<AuthContextType> = {}): AuthContextType => ({
    user: null,
    token: null,
    isAuthenticated: false,
    isLoading: false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: vi.fn(),
    clearError: vi.fn(),
    ...overrides,
});

const renderLoginPage = (authContextOverrides: Partial<AuthContextType> = {}) => {
    const authContextValue = createMockAuthContext(authContextOverrides);

    return render(
        <MemoryRouter initialEntries={['/connexion']}>
            <NotificationContext.Provider value={mockNotificationContext}>
                <AuthContext.Provider value={authContextValue}>
                    <LoginPage />
                </AuthContext.Provider>
            </NotificationContext.Provider>
        </MemoryRouter>
    );
};

describe('LoginPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('affiche correctement le formulaire de connexion et les éléments essentiels', () => {
        renderLoginPage();

        expect(screen.getByText('Connexion à SkillSwap')).toBeTruthy();
        expect(screen.getByLabelText(/Adresse email universitaire/i)).toBeTruthy();
        expect(screen.getByPlaceholderText('••••••••')).toBeTruthy();
        expect(screen.getByRole('button', { name: /Se connecter/i })).toBeTruthy();
        expect(screen.getByText(/Créer un compte \(\+2 crédits offerts\)/i)).toBeTruthy();
    });

    it('affiche des erreurs de validation locale si les champs sont vides', async () => {
        renderLoginPage();

        const submitBtn = screen.getByRole('button', { name: /Se connecter/i });
        fireEvent.click(submitBtn);

        await waitFor(() => {
            expect(screen.getByText("L'adresse email est obligatoire.")).toBeTruthy();
            expect(screen.getByText("Le mot de passe est obligatoire.")).toBeTruthy();
        });
    });

    it('gère une connexion réussie et déclenche la notification de bienvenue', async () => {
        const mockLogin = vi.fn().mockResolvedValue({
            id: 1,
            prenom: 'Camille',
            nom: 'Martin',
            email: 'camille@univ.fr',
            role: 'etudiant',
            statut: 'actif',
        });

        renderLoginPage({ login: mockLogin });

        fireEvent.change(screen.getByLabelText(/Adresse email universitaire/i), {
            target: { value: 'camille@univ.fr' },
        });
        fireEvent.change(screen.getByPlaceholderText('••••••••'), {
            target: { value: 'secret1234' },
        });

        const submitBtn = screen.getByRole('button', { name: /Se connecter/i });
        fireEvent.click(submitBtn);

        await waitFor(() => {
            expect(mockLogin).toHaveBeenCalledWith({
                email: 'camille@univ.fr',
                password: 'secret1234',
            });
            expect(mockSuccess).toHaveBeenCalledWith(
                expect.stringContaining('Bienvenue, Camille')
            );
        });
    });

    it('affiche une erreur globale 401 si les identifiants sont invalides', async () => {
        const axiosError = new axios.AxiosError('Unauthorized');
        axiosError.response = {
            status: 401,
            data: { message: 'Identifiants invalides.' },
            statusText: 'Unauthorized',
            headers: {},
            config: {} as any,
        };

        const mockLogin = vi.fn().mockRejectedValue(axiosError);

        renderLoginPage({ login: mockLogin });

        fireEvent.change(screen.getByLabelText(/Adresse email universitaire/i), {
            target: { value: 'inconnu@univ.fr' },
        });
        fireEvent.change(screen.getByPlaceholderText('••••••••'), {
            target: { value: 'mauvaismdp' },
        });

        fireEvent.click(screen.getByRole('button', { name: /Se connecter/i }));

        await waitFor(() => {
            expect(
                screen.getByText(/Identifiants incorrects. Veuillez vérifier votre adresse email/i)
            ).toBeTruthy();
        });
    });

    it('affiche le message de compte désactivé 403 (RG18)', async () => {
        const axiosError = new axios.AxiosError('Forbidden');
        axiosError.response = {
            status: 403,
            data: {
                message: 'Votre compte étudiant est actuellement désactivé (RG18). Veuillez contacter le support universitaire.',
            },
            statusText: 'Forbidden',
            headers: {},
            config: {} as any,
        };

        const mockLogin = vi.fn().mockRejectedValue(axiosError);

        renderLoginPage({ login: mockLogin });

        fireEvent.change(screen.getByLabelText(/Adresse email universitaire/i), {
            target: { value: 'banni@univ.fr' },
        });
        fireEvent.change(screen.getByPlaceholderText('••••••••'), {
            target: { value: 'password123' },
        });

        fireEvent.click(screen.getByRole('button', { name: /Se connecter/i }));

        await waitFor(() => {
            expect(
                screen.getByText(/Votre compte étudiant est actuellement désactivé \(RG18\)/i)
            ).toBeTruthy();
        });
    });

    it('affiche les erreurs 422 retournées par Laravel sous les champs respectifs', async () => {
        const axiosError = new axios.AxiosError('Unprocessable Entity');
        axiosError.response = {
            status: 422,
            data: {
                message: 'Les données fournies sont invalides.',
                errors: {
                    email: ["L'adresse email doit être une adresse universitaire valide."],
                },
            },
            statusText: 'Unprocessable Entity',
            headers: {},
            config: {} as any,
        };

        const mockLogin = vi.fn().mockRejectedValue(axiosError);

        renderLoginPage({ login: mockLogin });

        fireEvent.change(screen.getByLabelText(/Adresse email universitaire/i), {
            target: { value: 'test@invalid.com' },
        });
        fireEvent.change(screen.getByPlaceholderText('••••••••'), {
            target: { value: 'password123' },
        });

        fireEvent.click(screen.getByRole('button', { name: /Se connecter/i }));

        await waitFor(() => {
            expect(
                screen.getByText("L'adresse email doit être une adresse universitaire valide.")
            ).toBeTruthy();
        });
    });
});
