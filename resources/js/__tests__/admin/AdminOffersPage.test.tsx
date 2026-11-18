/**
 * Tests Unitaires & Intégration Frontend — Back-Office : Modération des Offres (RG17)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AdminOffersPage } from '../../pages/admin/AdminOffersPage';
import { adminApi } from '../../api/admin.api';
import { Offre } from '../../types/offre.types';

vi.mock('../../api/admin.api', () => {
    const api = {
        getOffres: vi.fn(),
        getOffre: vi.fn(),
        archiverOffre: vi.fn(),
        getCategories: vi.fn(),
    };
    return {
        adminApi: api,
        default: api,
    };
});

const mockOffres: Offre[] = [
    {
        id: 1,
        etudiant_id: 2,
        categorie_id: 1,
        titre: 'Initiation à React 19 et Hooks',
        competence: 'React 19',
        description: 'Apprenez les fondamentaux de React.',
        niveau: 'debutant',
        duree: 1,
        mode: 'distanciel',
        statut: 'publiee',
        categorie: { id: 1, nom: 'Informatique' },
        intervenant: {
            id: 2,
            nom: 'Durand',
            prenom: 'Julien',
            email: 'julien.durand@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Informatique',
        },
        creneaux: [
            {
                id: 10,
                offre_id: 1,
                date: '2026-12-01',
                heure_debut: '14:00',
                heure_fin: '15:00',
                lieu_public: 'En ligne',
                est_disponible: true,
            },
        ],
    },
];

describe('AdminOffersPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(adminApi.getCategories).mockResolvedValue({
            total: 1,
            categories: [{ id: 1, nom: 'Informatique' }],
        });
        vi.mocked(adminApi.getOffres).mockResolvedValue({
            total: 1,
            current_page: 1,
            last_page: 1,
            per_page: 15,
            offres: mockOffres,
        });
    });

    it('affiche la liste des offres avec intervenant et statut pour modération', async () => {
        render(
            <MemoryRouter>
                <AdminOffersPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Modération du Catalogue des Offres')).toBeTruthy();
            expect(screen.getByText('Initiation à React 19 et Hooks')).toBeTruthy();
            expect(screen.getByText('Julien Durand')).toBeTruthy();
            expect(screen.getByText(/Règle de gestion RG17/i)).toBeTruthy();
        });
    });

    it('ouvre la modale d’archivage et exécute l’archivage modérateur (RG17)', async () => {
        vi.mocked(adminApi.archiverOffre).mockResolvedValue({
            message: "L'offre a été archivée avec succès (RG17).",
            offre: { ...mockOffres[0], statut: 'archivee' },
        });

        render(
            <MemoryRouter>
                <AdminOffersPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Initiation à React 19 et Hooks')).toBeTruthy();
        });

        const archiveBtn = screen.getByRole('button', { name: /Archiver/i });
        fireEvent.click(archiveBtn);

        await waitFor(() => {
            expect(screen.getByText("Confirmer l'archivage de l'offre (RG17)")).toBeTruthy();
            expect(screen.getByText(/Application de la règle RG17/i)).toBeTruthy();
        });

        const confirmBtn = screen.getByRole('button', { name: /Confirmer l'archivage/i });
        fireEvent.click(confirmBtn);

        await waitFor(() => {
            expect(adminApi.archiverOffre).toHaveBeenCalledWith(1);
        });
    });
});
