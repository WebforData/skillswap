/**
 * Tests Unitaires & Intégration Frontend — Réservations & Litiges (ReservationsPage)
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ReservationsPage } from '../../pages/student/ReservationsPage';
import { AuthContext, AuthContextType } from '../../contexts/AuthContext';
import { NotificationContext, NotificationContextType } from '../../contexts/NotificationContext';
import { reservationsApi } from '../../api/reservations.api';
import { Reservation } from '../../types/reservation.types';

vi.mock('../../api/reservations.api', () => ({
    reservationsApi: {
        getMesReservations: vi.fn(),
        accepter: vi.fn(),
        refuser: vi.fn(),
        retirer: vi.fn(),
        annuler: vi.fn(),
        confirmer: vi.fn(),
        declarerLitige: vi.fn(),
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
        id: 1,
        prenom: 'Alex',
        nom: 'Durand',
        email: 'alex@univ.fr',
        role: 'etudiant',
        statut: 'actif',
        filiere: 'Informatique',
        solde_disponible: 3,
        credits_bloques: 2,
    },
    token: 'fake-token',
    isAuthenticated: true,
    isLoading: false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: vi.fn().mockResolvedValue(null),
    clearError: vi.fn(),
    ...overrides,
});

const mockIncomingReservations: Reservation[] = [
    {
        id: 201,
        creneau_id: 10,
        beneficiaire_id: 2,
        statut: 'en_attente',
        cout_credits: 1,
        confirmation_intervenant: false,
        confirmation_beneficiaire: false,
        offre: {
            id: 1,
            etudiant_id: 1,
            categorie_id: 1,
            titre: 'Bases de Données Relationnelles SQL',
            competence: 'SQL',
            description: 'Comprendre les jointures et index.',
            niveau: 'debutant',
            duree: 1,
            mode: 'distanciel',
        },
        creneau: {
            id: 10,
            offre_id: 1,
            date: '2026-11-27',
            heure_debut: '10:00:00',
            heure_fin: '11:00:00',
            lieu_public: 'Visioconférence Google Meet',
        },
        beneficiaire: {
            id: 2,
            nom: 'Lefebvre',
            prenom: 'Chloé',
            email: 'chloe@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Économie',
        },
    },
    {
        id: 202,
        creneau_id: 11,
        beneficiaire_id: 3,
        statut: 'acceptee',
        cout_credits: 2,
        confirmation_intervenant: false,
        confirmation_beneficiaire: true,
        offre: {
            id: 2,
            etudiant_id: 1,
            categorie_id: 1,
            titre: 'Programmation Orientée Objet Java',
            competence: 'Java',
            description: 'Classes, héritage et polymorphisme.',
            niveau: 'intermediaire',
            duree: 2,
            mode: 'presentiel',
        },
        creneau: {
            id: 11,
            offre_id: 2,
            date: '2026-11-28',
            heure_debut: '14:00:00',
            heure_fin: '16:00:00',
            lieu_public: 'Bibliothèque Centrale',
        },
        beneficiaire: {
            id: 3,
            nom: 'Garnier',
            prenom: 'Marc',
            email: 'marc@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Informatique',
        },
    },
];

const renderReservationsPage = () => {
    return render(
        <MemoryRouter initialEntries={['/app/reservations']}>
            <NotificationContext.Provider value={mockNotificationContext}>
                <AuthContext.Provider value={createMockAuthContext()}>
                    <ReservationsPage />
                </AuthContext.Provider>
            </NotificationContext.Provider>
        </MemoryRouter>
    );
};

describe('ReservationsPage', () => {
    afterEach(() => { vi.useRealTimers(); });

    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-11-20T12:00:00'));
        vi.clearAllMocks();
        vi.mocked(reservationsApi.getMesReservations).mockResolvedValue({
            total: 2,
            current_page: 1,
            last_page: 1,
            per_page: 10,
            reservations: mockIncomingReservations,
        });
        // Mock global confirm pour les boîtes de dialogue natives
        vi.spyOn(window, 'confirm').mockImplementation(() => true);
    });

    it('affiche les demandes reçues avec leurs statuts et coûts respectifs', async () => {
        renderReservationsPage();

        await waitFor(() => {
            expect(screen.getByText('Bases de Données Relationnelles SQL')).toBeTruthy();
            expect(screen.getByText('Programmation Orientée Objet Java')).toBeTruthy();
            expect(screen.getByText('Chloé Lefebvre')).toBeTruthy();
            expect(screen.getByText('Marc Garnier')).toBeTruthy();
            expect(screen.getByText('1 crédit')).toBeTruthy();
            expect(screen.getByText('2 crédits')).toBeTruthy();
        });
    });

    it("permet à l'intervenant d'accepter une réservation en attente (RG06, RG07)", async () => {
        vi.mocked(reservationsApi.accepter).mockResolvedValue({
            message: 'Réservation acceptée',
            reservation: { ...mockIncomingReservations[0], statut: 'acceptee' },
        });

        renderReservationsPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /^Accepter$/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /^Accepter$/i }));

        await waitFor(() => {
            expect(reservationsApi.accepter).toHaveBeenCalledWith(201);
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Demande acceptée !')
            );
        });
    });

    it("permet de refuser une demande avec un motif personnalisé (RG09)", async () => {
        vi.mocked(reservationsApi.refuser).mockResolvedValue({
            message: 'Réservation refusée',
            reservation: { ...mockIncomingReservations[0], statut: 'refusee', motif_refus: 'Indisponible' },
        });

        renderReservationsPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /^Refuser$/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /^Refuser$/i }));

        await waitFor(() => {
            expect(screen.getByText('Refuser la demande de réservation')).toBeTruthy();
        });

        const motifInput = screen.getByPlaceholderText(/Motif du refus/i);
        fireEvent.change(motifInput, { target: { value: 'Créneau déjà occupé par un examen' } });

        const confirmRefusBtn = screen.getByRole('button', { name: /Confirmer le refus/i });
        fireEvent.click(confirmRefusBtn);

        await waitFor(() => {
            expect(reservationsApi.refuser).toHaveBeenCalledWith(
                201,
                'Créneau déjà occupé par un examen'
            );
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Demande de réservation refusée')
            );
        });
    });

    it("permet d'annuler une réservation acceptée avant le début avec restitution des crédits (RG10)", async () => {
        vi.mocked(reservationsApi.annuler).mockResolvedValue({
            message: 'Séance annulée',
            reservation: { ...mockIncomingReservations[1], statut: 'annulee' },
        });

        renderReservationsPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Annuler \(RG10\)/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Annuler \(RG10\)/i }));

        await waitFor(() => {
            expect(screen.getByText(/Annuler la séance \(Règle RG10\)/i)).toBeTruthy();
            expect(screen.getByText(/intégralement restitués/i)).toBeTruthy();
        });

        const cancelConfirmBtn = screen.getByRole('button', { name: /Confirmer l'annulation/i });
        fireEvent.click(cancelConfirmBtn);

        await waitFor(() => {
            expect(reservationsApi.annuler).toHaveBeenCalledWith(202, '');
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Séance annulée avant le début')
            );
        });
    });

    it('gère la confirmation post-séance selon RG11 (bilatérale)', async () => {
        vi.setSystemTime(new Date('2026-11-29T12:00:00'));
        vi.mocked(reservationsApi.confirmer).mockResolvedValue({
            message: 'Séance confirmée',
            cloturee: true, // Le bénéficiaire avait déjà confirmé, donc clôture + transfert !
            reservation: { ...mockIncomingReservations[1], statut: 'realisee' },
        });

        renderReservationsPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Confirmer la séance/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Confirmer la séance/i }));

        await waitFor(() => {
            expect(reservationsApi.confirmer).toHaveBeenCalledWith(202);
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Séance validée par les deux participants')
            );
        });
    });

    it('ouvre un litige sur une séance acceptée avec description explicative (RG12)', async () => {
        vi.setSystemTime(new Date('2026-11-29T12:00:00'));
        vi.mocked(reservationsApi.declarerLitige).mockResolvedValue({
            message: 'Litige ouvert',
            litige: {
                id: 1,
                reservation_id: 202,
                declarant_id: 1,
                description: 'Le bénéficiaire ne s’est pas présenté au rendez-vous.',
                statut: 'ouvert',
            } as any,
        });

        renderReservationsPage();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Litige \(RG12\)/i })).toBeTruthy();
        });

        fireEvent.click(screen.getByRole('button', { name: /Litige \(RG12\)/i }));

        await waitFor(() => {
            expect(screen.getByText(/Déclarer un litige sur cette séance \(RG12\)/i)).toBeTruthy();
        });

        const descInput = screen.getByPlaceholderText(/Expliquez ce qui s'est produit/i);
        fireEvent.change(descInput, {
            target: { value: 'Le bénéficiaire ne s’est pas présenté au rendez-vous convenu.' },
        });

        const submitDisputeBtn = screen.getByRole('button', { name: /Ouvrir le litige/i });
        fireEvent.click(submitDisputeBtn);

        await waitFor(() => {
            expect(reservationsApi.declarerLitige).toHaveBeenCalledWith(
                202,
                'Le bénéficiaire ne s’est pas présenté au rendez-vous convenu.'
            );
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Litige ouvert avec succès')
            );
        });
    });
});
