/**
 * Tests Unitaires & Intégration Frontend — Page d'Inscription (RegisterPage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { RegisterPage } from '../../pages/auth/RegisterPage';
import { AuthContext, AuthContextType } from '../../contexts/AuthContext';
import { NotificationContext, NotificationContextType } from '../../contexts/NotificationContext';
import axios from 'axios';

const mockSuccess = vi.fn();
const mockError = vi.fn();

const mockNotificationContext: NotificationContextType = {
    showNotification: vi.fn(),
    removeNotification: vi.fn(),
    success: mockSuccess,
    error: mockError,
    warning: vi.fn(),
    info: vi.fn(),
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

const renderRegisterPage = (authContextOverrides: Partial<AuthContextType> = {}) => {
    const authContextValue = createMockAuthContext(authContextOverrides);

    return render(
        <MemoryRouter initialEntries={['/inscription']}>
            <NotificationContext.Provider value={mockNotificationContext}>
                <AuthContext.Provider value={authContextValue}>
                    <RegisterPage />
                </AuthContext.Provider>
            </NotificationContext.Provider>
        </MemoryRouter>
    );
};

describe('RegisterPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("affiche le formulaire d'inscription complet et la bannière du bonus RG01", () => {
        renderRegisterPage();

        expect(screen.getByText('Rejoindre SkillSwap Campus')).toBeTruthy();
        expect(screen.getByText(/Bonus de bienvenue \(Règle RG01\)/i)).toBeTruthy();
        expect(screen.getByText(/2 crédits d'échange/i)).toBeTruthy();

        expect(screen.getByPlaceholderText('Dupont')).toBeTruthy();
        expect(screen.getByPlaceholderText('Alexandre')).toBeTruthy();
        expect(screen.getByPlaceholderText('alexandre.dupont@etudiant.univ.fr')).toBeTruthy();
        expect(screen.getByPlaceholderText('8 caractères minimum')).toBeTruthy();
        expect(screen.getByPlaceholderText('Répétez le mot de passe')).toBeTruthy();
        expect(screen.getByRole('button', { name: /Créer mon compte \(\+2 crédits\)/i })).toBeTruthy();
    });

    it('bloque la soumission si le mot de passe fait moins de 8 caractères ou si la confirmation ne correspond pas', async () => {
        renderRegisterPage();

        fireEvent.change(screen.getByPlaceholderText('Dupont'), { target: { value: 'Dupont' } });
        fireEvent.change(screen.getByPlaceholderText('Alexandre'), { target: { value: 'Alexandre' } });
        fireEvent.change(screen.getByPlaceholderText('alexandre.dupont@etudiant.univ.fr'), {
            target: { value: 'alex@univ.fr' },
        });
        fireEvent.change(screen.getByPlaceholderText('8 caractères minimum'), {
            target: { value: 'court' },
        });
        fireEvent.change(screen.getByPlaceholderText('Répétez le mot de passe'), {
            target: { value: 'autre' },
        });

        fireEvent.click(screen.getByRole('button', { name: /Créer mon compte/i }));

        await waitFor(() => {
            expect(
                screen.getByText(/Le mot de passe doit comporter au moins 8 caractères/i)
            ).toBeTruthy();
            expect(
                screen.getByText(/La confirmation du mot de passe ne correspond pas/i)
            ).toBeTruthy();
        });
    });

    it('exécute une inscription réussie avec attribution des 2 crédits de bienvenue (RG01)', async () => {
        const mockRegister = vi.fn().mockResolvedValue({
            id: 2,
            prenom: 'Lucas',
            nom: 'Bernard',
            email: 'lucas@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            portefeuille: {
                solde_disponible: 2,
                credits_bloques: 0,
                solde_total: 2,
            },
        });

        renderRegisterPage({ register: mockRegister });

        fireEvent.change(screen.getByPlaceholderText('Dupont'), { target: { value: 'Bernard' } });
        fireEvent.change(screen.getByPlaceholderText('Alexandre'), { target: { value: 'Lucas' } });
        fireEvent.change(screen.getByPlaceholderText('alexandre.dupont@etudiant.univ.fr'), {
            target: { value: 'lucas@univ.fr' },
        });
        fireEvent.change(screen.getByPlaceholderText('8 caractères minimum'), {
            target: { value: 'password123' },
        });
        fireEvent.change(screen.getByPlaceholderText('Répétez le mot de passe'), {
            target: { value: 'password123' },
        });
        fireEvent.change(screen.getByPlaceholderText(/Ex : Licence Informatique/i), {
            target: { value: 'Licence Informatique' },
        });

        fireEvent.click(screen.getByRole('button', { name: /Créer mon compte/i }));

        await waitFor(() => {
            expect(mockRegister).toHaveBeenCalledWith(
                expect.objectContaining({
                    nom: 'Bernard',
                    prenom: 'Lucas',
                    email: 'lucas@univ.fr',
                    password: 'password123',
                    filiere: 'Licence Informatique',
                })
            );
            expect(mockSuccess).toHaveBeenCalledWith(
                expect.stringContaining('2 crédits de bienvenue ont été crédités (RG01)')
            );
        });
    });

    it("affiche l'erreur 422 de Laravel en cas d'email déjà utilisé (RG01)", async () => {
        const axiosError = new axios.AxiosError('Unprocessable Entity');
        axiosError.response = {
            status: 422,
            data: {
                message: 'Les données fournies sont invalides.',
                errors: {
                    email: [
                        'Cette adresse électronique est déjà associée à un compte existant (RG01).',
                    ],
                },
            },
            statusText: 'Unprocessable Entity',
            headers: {},
            config: {} as any,
        };

        const mockRegister = vi.fn().mockRejectedValue(axiosError);

        renderRegisterPage({ register: mockRegister });

        fireEvent.change(screen.getByPlaceholderText('Dupont'), { target: { value: 'Dupont' } });
        fireEvent.change(screen.getByPlaceholderText('Alexandre'), { target: { value: 'Jean' } });
        fireEvent.change(screen.getByPlaceholderText('alexandre.dupont@etudiant.univ.fr'), {
            target: { value: 'existant@univ.fr' },
        });
        fireEvent.change(screen.getByPlaceholderText('8 caractères minimum'), {
            target: { value: 'password123' },
        });
        fireEvent.change(screen.getByPlaceholderText('Répétez le mot de passe'), {
            target: { value: 'password123' },
        });

        fireEvent.click(screen.getByRole('button', { name: /Créer mon compte/i }));

        await waitFor(() => {
            expect(
                screen.getByText(
                    'Cette adresse électronique est déjà associée à un compte existant (RG01).'
                )
            ).toBeTruthy();
        });
    });
});
