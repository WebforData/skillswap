/**
 * Tests Unitaires & Intégration Frontend — Back-Office : Gestion des Catégories
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AdminCategoriesPage } from '../../pages/admin/AdminCategoriesPage';
import { adminApi } from '../../api/admin.api';
import { Categorie } from '../../types/offre.types';

vi.mock('../../api/admin.api', () => {
    const api = {
        getCategories: vi.fn(),
        creerCategorie: vi.fn(),
        modifierCategorie: vi.fn(),
        supprimerCategorie: vi.fn(),
    };
    return {
        adminApi: api,
        default: api,
    };
});

const mockCategories: Categorie[] = [
    {
        id: 1,
        nom: 'Informatique & Web',
        offres_count: 5,
    },
    {
        id: 2,
        nom: 'Langues Étrangères',
        offres_count: 0,
    },
];

describe('AdminCategoriesPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(adminApi.getCategories).mockResolvedValue({
            total: 2,
            categories: mockCategories,
        });
    });

    it('affiche la liste des catégories et le nombre d’offres rattachées', async () => {
        render(
            <MemoryRouter>
                <AdminCategoriesPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Gestion des Catégories de Compétences')).toBeTruthy();
            expect(screen.getByText('Informatique & Web')).toBeTruthy();
            expect(screen.getByText('5 offres')).toBeTruthy();
            expect(screen.getByText('Langues Étrangères')).toBeTruthy();
            expect(screen.getByText('0 offre')).toBeTruthy();
        });
    });

    it('permet de créer une nouvelle catégorie', async () => {
        vi.mocked(adminApi.creerCategorie).mockResolvedValue({
            message: 'Catégorie créée avec succès.',
            categorie: { id: 3, nom: 'Mathématiques & Statistiques', offres_count: 0 },
        });

        render(
            <MemoryRouter>
                <AdminCategoriesPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Nouvelle Catégorie')).toBeTruthy();
        });

        fireEvent.click(screen.getByText('Nouvelle Catégorie'));

        await waitFor(() => {
            expect(screen.getByText('Créer une nouvelle catégorie')).toBeTruthy();
        });

        const input = screen.getByPlaceholderText(/Ex : Informatique/i);
        fireEvent.change(input, { target: { value: 'Mathématiques & Statistiques' } });

        const submitBtn = screen.getByRole('button', { name: /^Créer la catégorie$/i });
        fireEvent.click(submitBtn);

        await waitFor(() => {
            expect(adminApi.creerCategorie).toHaveBeenCalledWith('Mathématiques & Statistiques');
        });
    });

    it('gère l’erreur 409 lors de la tentative de suppression d’une catégorie liée à des offres', async () => {
        const error409 = {
            response: {
                status: 409,
                data: {
                    message: "Suppression impossible : 5 offre(s) rattachée(s) à cette catégorie.",
                },
            },
        };
        vi.mocked(adminApi.supprimerCategorie).mockRejectedValue(error409);

        render(
            <MemoryRouter>
                <AdminCategoriesPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Informatique & Web')).toBeTruthy();
        });

        const deleteButtons = screen.getAllByRole('button', { name: /Supprimer/i });
        fireEvent.click(deleteButtons[0]);

        await waitFor(() => {
            expect(screen.getByText('Confirmer la suppression')).toBeTruthy();
            expect(screen.getByText(/cette catégorie contient 5 offre\(s\)/i)).toBeTruthy();
        });

        const confirmBtn = screen.getByRole('button', { name: /Supprimer définitivement/i });
        fireEvent.click(confirmBtn);

        await waitFor(() => {
            expect(adminApi.supprimerCategorie).toHaveBeenCalledWith(1);
            expect(screen.getByText(/Suppression impossible : 5 offre\(s\) rattachée\(s\)/i)).toBeTruthy();
        });
    });
});
