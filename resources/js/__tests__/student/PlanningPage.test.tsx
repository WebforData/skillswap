/**
 * Tests Unitaires & Intégration Frontend — Planning & RG19 (PlanningPage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { PlanningPage } from '../../pages/student/PlanningPage';
import { planningApi } from '../../api/planning.api';
import { PlanningItem } from '../../types/reservation.types';

vi.mock('../../api/planning.api', () => ({
    planningApi: {
        getPlanning: vi.fn(),
    },
}));

const mockPlanningItems: PlanningItem[] = [
    {
        reservation_id: 101,
        statut: 'acceptee',
        cout_credits: 1,
        role: 'beneficiaire',
        autre_participant: {
            id: 20,
            nom: 'Dubois',
            prenom: 'Emma',
            filiere: 'Licence Droit',
        },
        offre: {
            id: 5,
            titre: 'Introduction au Droit Constitutionnel',
            competence: 'Droit public',
            niveau: 'debutant',
            mode: 'distanciel',
            duree: 1,
        },
        creneau: {
            id: 501,
            date: '2026-11-26',
            heure_debut: '10:00:00',
            heure_fin: '11:00:00',
            lieu_public: 'Visioconférence Meet',
            lieu_ou_lien_prive: 'https://meet.google.com/droit-constitutionnel-2026', // Révélé car acceptée (RG19)
        },
        actions_possibles: {
            peut_annuler: true,
            peut_confirmer: false,
            peut_ouvrir_litige: false,
            peut_evaluer: false,
        },
    },
    {
        reservation_id: 102,
        statut: 'en_attente',
        cout_credits: 2,
        role: 'beneficiaire',
        autre_participant: {
            id: 21,
            nom: 'Moreau',
            prenom: 'Lucas',
            filiere: 'Informatique',
        },
        offre: {
            id: 6,
            titre: 'Machine Learning avec PyTorch',
            competence: 'Python IA',
            niveau: 'avance',
            mode: 'presentiel',
            duree: 2,
        },
        creneau: {
            id: 502,
            date: '2026-11-29',
            heure_debut: '15:00:00',
            heure_fin: '17:00:00',
            lieu_public: 'Campus Sciences - Bâtiment Turing',
            lieu_ou_lien_prive: null, // Masqué car en attente (RG19)
        },
        actions_possibles: {
            peut_annuler: true,
            peut_confirmer: false,
            peut_ouvrir_litige: false,
            peut_evaluer: false,
        },
    },
];

describe('PlanningPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(planningApi.getPlanning).mockResolvedValue({
            total: 2,
            planning: mockPlanningItems,
        });
    });

    it('affiche le planning avec le respect de la règle de confidentialité RG19', async () => {
        render(
            <MemoryRouter initialEntries={['/app/planning']}>
                <PlanningPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Introduction au Droit Constitutionnel')).toBeTruthy();
            expect(screen.getByText('Machine Learning avec PyTorch')).toBeTruthy();

            // RG19 : Coordonnées privées débloquées pour la séance acceptée
            expect(screen.getByText(/Détails privés \(RG19\)/i)).toBeTruthy();
            expect(screen.getByText(/Rejoindre la visio/i)).toBeTruthy();

            // RG19 : Coordonnées privées masquées pour la séance en attente
            expect(
                screen.getByText(/Coordonnées privées masquées avant acceptation \(RG19\)/i)
            ).toBeTruthy();
        });
    });

    it('permet de filtrer le planning par rôle (intervenant vs bénéficiaire)', async () => {
        render(
            <MemoryRouter initialEntries={['/app/planning']}>
                <PlanningPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Introduction au Droit Constitutionnel')).toBeTruthy();
        });

        const teacherTab = screen.getByRole('button', { name: /En tant qu'intervenant/i });
        fireEvent.click(teacherTab);

        await waitFor(() => {
            expect(planningApi.getPlanning).toHaveBeenCalledWith(
                expect.objectContaining({
                    role: 'intervenant',
                })
            );
        });
    });

    it('permet de basculer entre séances à venir et historique', async () => {
        render(
            <MemoryRouter initialEntries={['/app/planning']}>
                <PlanningPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Introduction au Droit Constitutionnel')).toBeTruthy();
        });

        const historyBtn = screen.getByRole('button', { name: /^Historique$/i });
        fireEvent.click(historyBtn);

        await waitFor(() => {
            expect(planningApi.getPlanning).toHaveBeenCalledWith(
                expect.objectContaining({
                    filtre: 'historique',
                })
            );
        });
    });
});
