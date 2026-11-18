/**
 * Tests Unitaires & Intégration Frontend — Back-Office : Arbitrage des Litiges (RG12, RG13, RG14)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AdminDisputesPage } from '../../pages/admin/AdminDisputesPage';
import { adminApi } from '../../api/admin.api';
import { Litige } from '../../types/admin.types';

vi.mock('../../api/admin.api', () => {
    const api = {
        getLitiges: vi.fn(),
        getLitige: vi.fn(),
        arbitrerLitige: vi.fn(),
    };
    return {
        adminApi: api,
        default: api,
    };
});

const mockLitigesOuverts: Litige[] = [
    {
        id: 1,
        reservation_id: 10,
        declarant_id: 3,
        description: "L'intervenant ne s'est jamais présenté sur le lieu de rendez-vous convenu.",
        statut: 'ouvert',
        declarant: {
            id: 3,
            nom: 'Moreau',
            prenom: 'Camille',
            email: 'camille.moreau@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Sciences',
        },
        reservation: {
            id: 10,
            creneau_id: 5,
            beneficiaire_id: 3,
            cout_credits: 2,
            statut: 'en_litige',
            date_demande: '2026-11-15T10:00:00Z',
            confirmation_intervenant: false,
            confirmation_beneficiaire: false,
            creneau: {
                id: 5,
                offre_id: 2,
                date: '2026-11-17',
                heure_debut: '14:00',
                heure_fin: '16:00',
                lieu_public: 'Bibliothèque Universitaire',
                offre: {
                    id: 2,
                    etudiant_id: 4,
                    categorie_id: 1,
                    titre: 'Cours d’Algèbre Linéaire',
                    competence: 'Mathématiques',
                    description: 'Matrices et espaces vectoriels',
                    niveau: 'intermediaire',
                    duree: 2,
                    mode: 'presentiel',
                    intervenant: {
                        id: 4,
                        nom: 'Leroy',
                        prenom: 'Maxime',
                        email: 'maxime.leroy@univ.fr',
                        role: 'etudiant',
                        statut: 'actif',
                        filiere: 'Mathématiques',
                    },
                },
            },
            beneficiaire: {
                id: 3,
                nom: 'Moreau',
                prenom: 'Camille',
                email: 'camille.moreau@univ.fr',
                role: 'etudiant',
                statut: 'actif',
                filiere: 'Sciences',
            },
        },
    },
];

describe('AdminDisputesPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(adminApi.getLitiges).mockResolvedValue({
            total: 1,
            current_page: 1,
            last_page: 1,
            per_page: 15,
            litiges: mockLitigesOuverts,
        });
    });

    it('affiche les dossiers de litige ouverts et les enjeux séquestrés (RG12, RG13)', async () => {
        render(
            <MemoryRouter>
                <AdminDisputesPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Arbitrage Administratif des Litiges')).toBeTruthy();
            expect(screen.getByText(/Règles d'arbitrage impératives \(RG13 & RG14\)/i)).toBeTruthy();
            expect(screen.getByText('Litige #1')).toBeTruthy();
            expect(screen.getAllByText('Camille Moreau').length).toBeGreaterThanOrEqual(1);
            expect(screen.getByText('2 crédits')).toBeTruthy();
            expect(screen.getByText('Arbitrage Requis')).toBeTruthy();
        });
    });

    it('ouvre la chambre d’arbitrage et exécute la décision arbitrale définitive avec motif (RG13, RG14)', async () => {
        vi.mocked(adminApi.arbitrerLitige).mockResolvedValue({
            message: 'Litige résolu en faveur du bénéficiaire : les crédits ont été intégralement restitués.',
            litige: {
                ...mockLitigesOuverts[0],
                statut: 'resolu',
                decision: 'restitution_beneficiaire',
                motif_decision: 'Absence constatée et confirmée de l’intervenant.',
            },
        });

        render(
            <MemoryRouter>
                <AdminDisputesPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Litige #1')).toBeTruthy();
        });

        const arbitrerBtn = screen.getByRole('button', { name: /^Arbitrer$/i });
        fireEvent.click(arbitrerBtn);

        await waitFor(() => {
            expect(screen.getByText(/Rendre la décision arbitrale — Dossier #1/i)).toBeTruthy();
            expect(screen.getByText(/Arbitrage Définitif et Irréversible \(RG14\)/i)).toBeTruthy();
            expect(screen.getByText(/Restitution des crédits au bénéficiaire/i)).toBeTruthy();
        });

        // Remplir le motif d'arbitrage
        const motifTextarea = screen.getByPlaceholderText(/Précisez les constatations/i);
        fireEvent.change(motifTextarea, {
            target: { value: 'Absence constatée et confirmée de l’intervenant sur le lieu.' },
        });

        const submitBtn = screen.getByRole('button', { name: /Exécuter l'arbitrage définitif/i });
        fireEvent.click(submitBtn);

        await waitFor(() => {
            expect(adminApi.arbitrerLitige).toHaveBeenCalledWith(1, {
                decision: 'restitution_beneficiaire',
                motif: 'Absence constatée et confirmée de l’intervenant sur le lieu.',
            });
        });
    });

    it('bloque la soumission si le motif de l’arbitrage fait moins de 5 caractères (RG13)', async () => {
        render(
            <MemoryRouter>
                <AdminDisputesPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Litige #1')).toBeTruthy();
        });

        const arbitrerBtn = screen.getByRole('button', { name: /^Arbitrer$/i });
        fireEvent.click(arbitrerBtn);

        await waitFor(() => {
            expect(screen.getByText(/Rendre la décision arbitrale — Dossier #1/i)).toBeTruthy();
        });

        const motifTextarea = screen.getByPlaceholderText(/Précisez les constatations/i);
        fireEvent.change(motifTextarea, { target: { value: 'Non' } });

        const submitBtn = screen.getByRole('button', { name: /Exécuter l'arbitrage définitif/i });
        fireEvent.click(submitBtn);

        await waitFor(() => {
            expect(screen.getByText(/Le motif officiel de l'arbitrage doit comporter au moins 5 caractères/i)).toBeTruthy();
            expect(adminApi.arbitrerLitige).not.toHaveBeenCalled();
        });
    });
});
