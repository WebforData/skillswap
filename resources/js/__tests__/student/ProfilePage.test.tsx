/**
 * Tests Unitaires & Intégration Frontend — Profil & Portefeuille RG16 (ProfilePage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ProfilePage } from '../../pages/student/ProfilePage';
import { AuthContext, AuthContextType } from '../../contexts/AuthContext';
import { evaluationsApi } from '../../api/evaluations.api';
import { Evaluation } from '../../types/evaluation.types';

vi.mock('../../api/evaluations.api', () => ({
    evaluationsApi: {
        getRecues: vi.fn(),
        getStats: vi.fn(),
    },
}));

const mockEvaluations: Evaluation[] = [
    {
        id: 1,
        reservation_id: 10,
        auteur_id: 2,
        cible_id: 1,
        note: 5,
        commentaire: 'Excellente séance de révision, explications très limpides !',
        created_at: '2026-11-20T10:00:00Z',
        auteur: {
            id: 2,
            prenom: 'Manon',
            nom: 'Petit',
            email: 'manon@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Droit',
        },
    },
    {
        id: 2,
        reservation_id: 11,
        auteur_id: 3,
        cible_id: 1,
        note: 4,
        commentaire: 'Très patient et ponctuel. Merci pour ton aide.',
        created_at: '2026-11-22T14:30:00Z',
        auteur: {
            id: 3,
            prenom: 'Paul',
            nom: 'Lemoine',
            email: 'paul@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Informatique',
        },
    },
];

const mockRefreshUser = vi.fn().mockResolvedValue(null);

const createMockAuthContext = (overrides: Partial<AuthContextType> = {}): AuthContextType => ({
    user: {
        id: 1,
        prenom: 'Alexandre',
        nom: 'Rousseau',
        email: 'alexandre.rousseau@univ.fr',
        role: 'etudiant',
        statut: 'actif',
        filiere: 'Master Sciences des Données',
        competences_proposees: 'Python, Machine Learning, SQL',
        competences_recherchees: 'Espagnol, Prise de parole',
        solde_disponible: 3,
        credits_bloques: 2,
        portefeuille: {
            solde_disponible: 3,
            credits_bloques: 2,
            solde_total: 5,
        },
    },
    token: 'fake-token',
    isAuthenticated: true,
    isLoading: false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: mockRefreshUser,
    clearError: vi.fn(),
    ...overrides,
});

describe('ProfilePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(evaluationsApi.getRecues).mockResolvedValue({
            data: mockEvaluations,
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 2,
            from: 1,
            to: 2,
        });
        vi.mocked(evaluationsApi.getStats).mockResolvedValue({
            etudiant_id: 1,
            nombre_evaluations: 2,
            note_moyenne: 4.5,
        });
    });

    it('affiche le portefeuille en temps réel avec solde disponible, bloqué et total (RG16)', async () => {
        render(
            <MemoryRouter initialEntries={['/app/profil']}>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <ProfilePage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Mon Profil & Portefeuille')).toBeTruthy();
            expect(screen.getByText('Solde Disponible')).toBeTruthy();
            expect(screen.getByText('Crédits Bloqués (Séquestre)')).toBeTruthy();
            expect(screen.getByText('3')).toBeTruthy();
            expect(screen.getByText('2')).toBeTruthy();
            expect(screen.getByText('5')).toBeTruthy();
            expect(screen.getByText(/Règle RG16 : Solde garanti strictement non négatif/i)).toBeTruthy();
        });
    });

    it("affiche les détails personnels de l'étudiant et ses compétences", async () => {
        render(
            <MemoryRouter initialEntries={['/app/profil']}>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <ProfilePage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Alexandre Rousseau')).toBeTruthy();
            expect(screen.getByText('alexandre.rousseau@univ.fr')).toBeTruthy();
            expect(screen.getByText('Master Sciences des Données')).toBeTruthy();
            expect(screen.getByText(/Python, Machine Learning, SQL/i)).toBeTruthy();
            expect(screen.getByText(/Espagnol, Prise de parole/i)).toBeTruthy();
        });
    });

    it('affiche les évaluations reçues avec note et avis de tiers (RG15)', async () => {
        render(
            <MemoryRouter initialEntries={['/app/profil']}>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <ProfilePage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Manon Petit')).toBeTruthy();
            expect(screen.getByText(/Excellente séance de révision/i)).toBeTruthy();
            expect(screen.getByText('Paul Lemoine')).toBeTruthy();
            expect(screen.getByText(/Très patient et ponctuel/i)).toBeTruthy();
            expect(screen.getByText('4.5 / 5')).toBeTruthy();
        });
    });

    it('permet d’actualiser les soldes via le bouton Actualiser les soldes', async () => {
        render(
            <MemoryRouter initialEntries={['/app/profil']}>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <ProfilePage />
                </AuthContext.Provider>
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Actualiser les soldes/i })).toBeTruthy();
        });

        const refreshBtn = screen.getByRole('button', { name: /Actualiser les soldes/i });
        fireEvent.click(refreshBtn);

        await waitFor(() => {
            expect(mockRefreshUser).toHaveBeenCalled();
            expect(evaluationsApi.getRecues).toHaveBeenCalled();
        });
    });
});
