/**
 * Tests Unitaires & Intégration Frontend — Back-Office : Gestion des Utilisateurs (RG18)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AdminUsersPage } from '../../pages/admin/AdminUsersPage';
import { adminApi } from '../../api/admin.api';
import { AuthContext } from '../../contexts/AuthContext';
import { User } from '../../types/auth.types';

vi.mock('../../api/admin.api', () => {
    const api = {
        getUsers: vi.fn(),
        getUser: vi.fn(),
        desactiverUser: vi.fn(),
        activerUser: vi.fn(),
    };
    return {
        adminApi: api,
        default: api,
    };
});

const mockCurrentAdmin: User = {
    id: 1,
    nom: 'Admin',
    prenom: 'Super',
    email: 'admin@univ-paris.fr',
    role: 'administrateur',
    statut: 'actif',
    filiere: 'Administration',
    portefeuille: { solde_disponible: 10, credits_bloques: 0, solde_total: 10 },
};

const mockUsersList: User[] = [
    {
        id: 1,
        nom: 'Admin',
        prenom: 'Super',
        email: 'admin@univ-paris.fr',
        role: 'administrateur',
        statut: 'actif',
        filiere: 'Administration',
        portefeuille: { solde_disponible: 10, credits_bloques: 0, solde_total: 10 },
    },
    {
        id: 2,
        nom: 'Dupont',
        prenom: 'Lucas',
        email: 'lucas.dupont@univ-paris.fr',
        role: 'etudiant',
        statut: 'actif',
        filiere: 'Master Informatique',
        portefeuille: { solde_disponible: 4, credits_bloques: 2, solde_total: 6 },
    },
    {
        id: 3,
        nom: 'Bernard',
        prenom: 'Chloé',
        email: 'chloe.bernard@univ-paris.fr',
        role: 'etudiant',
        statut: 'desactive',
        filiere: 'Licence Droit',
        portefeuille: { solde_disponible: 2, credits_bloques: 0, solde_total: 2 },
    },
];

const createMockAuthContext = (user: User = mockCurrentAdmin) => ({
    user,
    token: 'fake_admin_token',
    isAuthenticated: true,
    isLoading: false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: vi.fn(),
    clearError: vi.fn(),
});

describe('AdminUsersPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(adminApi.getUsers).mockResolvedValue({
            total: 3,
            current_page: 1,
            last_page: 1,
            per_page: 15,
            users: mockUsersList,
        });
    });

    it('affiche la liste des utilisateurs avec filtres et avertissement RG18', async () => {
        render(
            <MemoryRouter>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <AdminUsersPage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Gestion des Étudiants & Utilisateurs')).toBeTruthy();
            expect(screen.getByText(/Règle de gestion RG18/i)).toBeTruthy();
            expect(screen.getByText('Lucas Dupont')).toBeTruthy();
            expect(screen.getByText('Chloé Bernard')).toBeTruthy();
        });
    });

    it('interdit à l’administrateur connecté de désactiver son propre compte', async () => {
        render(
            <MemoryRouter>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <AdminUsersPage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Super Admin')).toBeTruthy();
        });

        // Le bouton Désactiver pour l'admin lui-même (id 1) est désactivé
        const deactButtons = screen.getAllByRole('button', { name: /Désactiver/i });
        // Le bouton de l'admin (isSelf) est disabled
        expect(deactButtons[0]).toHaveProperty('disabled', true);
    });

    it('ouvre la modale de confirmation et désactive un compte étudiant (RG18)', async () => {
        vi.mocked(adminApi.desactiverUser).mockResolvedValue({
            message: 'Compte désactivé avec succès (RG18).',
            user: { ...mockUsersList[1], statut: 'desactive' },
        });

        render(
            <MemoryRouter>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <AdminUsersPage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Lucas Dupont')).toBeTruthy();
        });

        const deactButtons = screen.getAllByRole('button', { name: /Désactiver/i });
        // Le 2e bouton correspond à Lucas Dupont
        fireEvent.click(deactButtons[1]);

        await waitFor(() => {
            expect(screen.getByText('Confirmer la désactivation du compte')).toBeTruthy();
            expect(screen.getByText(/Conséquences de la désactivation \(RG18\)/i)).toBeTruthy();
        });

        // Cliquer sur le bouton de confirmation finale
        const confirmBtn = screen.getByRole('button', { name: /Confirmer la désactivation/i });
        fireEvent.click(confirmBtn);

        await waitFor(() => {
            expect(adminApi.desactiverUser).toHaveBeenCalledWith(2);
        });
    });

    it('permet de réactiver un compte désactivé', async () => {
        vi.mocked(adminApi.activerUser).mockResolvedValue({
            message: 'Le compte a été réactivé avec succès.',
            user: { ...mockUsersList[2], statut: 'actif' },
        });

        render(
            <MemoryRouter>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <AdminUsersPage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Chloé Bernard')).toBeTruthy();
        });

        const reactiverBtn = screen.getByRole('button', { name: /Réactiver/i });
        fireEvent.click(reactiverBtn);

        await waitFor(() => {
            expect(adminApi.activerUser).toHaveBeenCalledWith(3);
        });
    });
});
