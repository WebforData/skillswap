/**
 * Tests Unitaires & Intégration Frontend — Détail Offre & Réservation (OffreDetailPage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { OffreDetailPage } from '../../pages/student/OffreDetailPage';
import { AuthContext, AuthContextType } from '../../contexts/AuthContext';
import { NotificationContext, NotificationContextType } from '../../contexts/NotificationContext';
import { offresApi } from '../../api/offres.api';
import { reservationsApi } from '../../api/reservations.api';
import { evaluationsApi } from '../../api/evaluations.api';
import { Offre } from '../../types/offre.types';
import axios from 'axios';

vi.mock('../../api/offres.api', () => ({
    offresApi: {
        getOffre: vi.fn(),
    },
}));

vi.mock('../../api/reservations.api', () => ({
    reservationsApi: {
        demanderReservation: vi.fn(),
    },
}));

vi.mock('../../api/evaluations.api', () => ({
    evaluationsApi: {
        getStats: vi.fn(),
    },
}));

const mockNotificationContext: NotificationContextType = {
    showNotification: vi.fn(),
    removeNotification: vi.fn(),
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
};

const createMockAuthContext = (overrides: Partial<AuthContextType> = {}): AuthContextType => ({
    user: {
        id: 50,
        prenom: 'Julien',
        nom: 'Roux',
        email: 'julien@univ.fr',
        role: 'etudiant',
        statut: 'actif',
        filiere: 'Informatique',
        solde_disponible: 4,
        portefeuille: {
            solde_disponible: 4,
            credits_bloques: 0,
            solde_total: 4,
        },
    },
    token: 'fake-token',
    isAuthenticated: true,
    isLoading: false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: vi.fn(),
    clearError: vi.fn(),
    ...overrides,
});

const mockOffre: Offre = {
    id: 1,
    etudiant_id: 10,
    categorie_id: 1,
    titre: 'Cours avancé de Python & FastAPI',
    competence: 'Python',
    description: 'Comprendre les architectures asynchrones et construire des API robustes.',
    niveau: 'avance',
    duree: 2,
    mode: 'distanciel',
    statut: 'publiee',
    categorie: { id: 1, nom: 'Informatique' },
    intervenant: {
        id: 10,
        nom: 'Vidal',
        prenom: 'Sarah',
        email: 'sarah@univ.fr',
        filiere: 'Master Génie Logiciel',
        role: 'etudiant',
        statut: 'actif',
    },
    creneaux: [
        {
            id: 101,
            offre_id: 1,
            date: '2026-11-25',
            heure_debut: '14:00:00',
            heure_fin: '16:00:00',
            lieu_public: 'Visioconférence Google Meet',
            lieu_ou_lien_prive: null, // Masqué par défaut RG19
        },
    ],
};

const renderOffreDetailPage = (authOverrides: Partial<AuthContextType> = {}) => {
    const authContextValue = createMockAuthContext(authOverrides);

    return render(
        <MemoryRouter initialEntries={['/offres/1']}>
            <NotificationContext.Provider value={mockNotificationContext}>
                <AuthContext.Provider value={authContextValue}>
                    <Routes>
                        <Route path="/offres/:id" element={<OffreDetailPage />} />
                    </Routes>
                </AuthContext.Provider>
            </NotificationContext.Provider>
        </MemoryRouter>
    );
};

describe('OffreDetailPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(offresApi.getOffre).mockResolvedValue({ offre: mockOffre });
        vi.mocked(evaluationsApi.getStats).mockResolvedValue({
            etudiant_id: 10,
            nombre_evaluations: 6,
            note_moyenne: 4.8,
        });
    });

    it("affiche les détails complets de l'offre et l'avertissement RG19", async () => {
        renderOffreDetailPage();

        expect(screen.getByText(/Chargement des détails de l'offre/i)).toBeTruthy();

        await waitFor(() => {
            expect(screen.getByText('Cours avancé de Python & FastAPI')).toBeTruthy();
            expect(screen.getByText('Sarah Vidal')).toBeTruthy();
            expect(screen.getByText('2 crédits')).toBeTruthy();
            expect(screen.getByText(/Protection de la vie privée \(RG19\)/i)).toBeTruthy();
            expect(screen.getByText(/Lieu public : Visioconférence Google Meet/i)).toBeTruthy();
            expect(screen.getByRole('button', { name: /Réserver/i })).toBeTruthy();
        });
    });

    it("ouvre la modale de réservation avec le coût et le solde de l'étudiant", async () => {
        renderOffreDetailPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Réserver/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Réserver/i }));

        await waitFor(() => {
            expect(screen.getByText('Confirmer votre demande de réservation')).toBeTruthy();
            expect(screen.getByText(/Votre solde disponible :/i)).toBeTruthy();
            expect(screen.getByText('4 crédits')).toBeTruthy();
            expect(screen.getByText(/2 crédits \(RG01\/RG02\)/i)).toBeTruthy();
            expect(screen.getByText(/Règle RG05/i)).toBeTruthy();
        });
    });

    it('confirme la demande de réservation avec succès et appelle reservationsApi', async () => {
        vi.mocked(reservationsApi.demanderReservation).mockResolvedValue({
            message: 'Demande de réservation enregistrée en attente (RG05).',
            reservation: {
                id: 501,
                creneau_id: 101,
                beneficiaire_id: 50,
                statut: 'en_attente',
                cout_credits: 2,
            } as any,
        });

        renderOffreDetailPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Réserver/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Réserver/i }));

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Confirmer la demande/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Confirmer la demande/i }));

        await waitFor(() => {
            expect(reservationsApi.demanderReservation).toHaveBeenCalledWith(101);
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('demande de réservation a été enregistrée avec succès')
            );
        });
    });

    it("affiche une erreur 422 si l'étudiant tente de réserver sa propre offre (RG03)", async () => {
        const axiosError = new axios.AxiosError('Unprocessable Entity');
        axiosError.response = {
            status: 422,
            data: {
                error: 'AutoReservationException',
                message: 'Un étudiant ne peut pas réserver sa propre offre (RG03).',
            },
            statusText: 'Unprocessable Entity',
            headers: {},
            config: {} as any,
        };

        vi.mocked(reservationsApi.demanderReservation).mockRejectedValue(axiosError);

        renderOffreDetailPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Réserver/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Réserver/i }));
        fireEvent.click(screen.getByRole('button', { name: /Confirmer la demande/i }));

        await waitFor(() => {
            expect(
                screen.getByText('Un étudiant ne peut pas réserver sa propre offre (RG03).')
            ).toBeTruthy();
        });
    });

    it('affiche une alerte préventive si le solde disponible est inférieur au coût (RG05)', async () => {
        // Étudiant avec 1 seul crédit pour une offre de 2 crédits
        renderOffreDetailPage({
            user: {
                id: 50,
                prenom: 'Julien',
                nom: 'Roux',
                email: 'julien@univ.fr',
                role: 'etudiant',
                statut: 'actif',
                filiere: 'Informatique',
                solde_disponible: 1,
                portefeuille: {
                    solde_disponible: 1,
                    credits_bloques: 0,
                    solde_total: 1,
                },
            },
        });

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Réserver/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Réserver/i }));

        await waitFor(() => {
            expect(
                screen.getByText(
                    /Votre solde disponible \(1 crédit\(s\)\) est inférieur au coût de la séance \(2 crédit\(s\)\)/i
                )
            ).toBeTruthy();
        });
    });
});
