/**
 * Tests Unitaires & Intégration Frontend — Page Catalogue (CataloguePage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { CataloguePage } from '../../pages/student/CataloguePage';
import { offresApi } from '../../api/offres.api';
import { LaravelPaginatedResponse } from '../../types/api.types';
import { Offre } from '../../types/offre.types';

vi.mock('../../api/offres.api', () => ({
    offresApi: {
        getCatalogue: vi.fn(),
    },
}));

const mockOffres: Offre[] = [
    {
        id: 1,
        etudiant_id: 10,
        categorie_id: 1,
        titre: 'Initiation à React et TypeScript',
        competence: 'React 19',
        description: 'Apprenez à créer des composants modernes et typés avec React.',
        niveau: 'debutant',
        duree: 1,
        mode: 'distanciel',
        statut: 'publiee',
        categorie: { id: 1, nom: 'Informatique' },
        intervenant: {
            id: 10,
            nom: 'Durand',
            prenom: 'Sophie',
            email: 'sophie@univ.fr',
            filiere: 'Master Informatique',
            role: 'etudiant',
            statut: 'actif',
        },
    },
    {
        id: 2,
        etudiant_id: 11,
        categorie_id: 2,
        titre: 'Algèbre Linéaire et Espaces Vectoriels',
        competence: 'Mathématiques L1',
        description: 'Séance approfondie de révision des partiels de mathématiques.',
        niveau: 'intermediaire',
        duree: 2,
        mode: 'presentiel',
        statut: 'publiee',
        categorie: { id: 2, nom: 'Mathématiques' },
        intervenant: {
            id: 11,
            nom: 'Lemoine',
            prenom: 'Thomas',
            email: 'thomas@univ.fr',
            filiere: 'Licence Mathématiques',
            role: 'etudiant',
            statut: 'actif',
        },
    },
];

const mockPaginatedResponse: LaravelPaginatedResponse<Offre> = {
    data: mockOffres,
    current_page: 1,
    last_page: 2,
    per_page: 9,
    total: 15,
    from: 1,
    to: 2,
};

describe('CataloguePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('charge et affiche la liste des offres avec leurs caractéristiques RG02', async () => {
        vi.mocked(offresApi.getCatalogue).mockResolvedValue(mockPaginatedResponse);

        render(
            <MemoryRouter initialEntries={['/catalogue']}>
                <CataloguePage />
            </MemoryRouter>
        );

        // Spinner d'abord
        expect(screen.getByText(/Chargement des offres en cours/i)).toBeTruthy();

        // Puis les offres
        await waitFor(() => {
            expect(screen.getByText('Initiation à React et TypeScript')).toBeTruthy();
            expect(screen.getByText('Algèbre Linéaire et Espaces Vectoriels')).toBeTruthy();
            expect(screen.getByText('1 crédit')).toBeTruthy();
            expect(screen.getByText('2 crédits')).toBeTruthy();
            expect(screen.getByText('Sophie Durand')).toBeTruthy();
            expect(screen.getByText('Thomas Lemoine')).toBeTruthy();
        });
    });

    it('applique un filtre de recherche par mot-clé', async () => {
        vi.mocked(offresApi.getCatalogue).mockResolvedValue(mockPaginatedResponse);

        render(
            <MemoryRouter initialEntries={['/catalogue']}>
                <CataloguePage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Initiation à React et TypeScript')).toBeTruthy();
        });

        const searchInput = screen.getByPlaceholderText(/Rechercher une compétence/i);
        fireEvent.change(searchInput, { target: { value: 'React' } });

        const searchBtn = screen.getByRole('button', { name: /^Rechercher$/i });
        fireEvent.click(searchBtn);

        await waitFor(() => {
            expect(offresApi.getCatalogue).toHaveBeenCalledWith(
                expect.objectContaining({
                    q: 'React',
                    page: 1,
                })
            );
        });
    });

    it('affiche un état vide soigné lorsque aucune offre ne correspond', async () => {
        vi.mocked(offresApi.getCatalogue).mockResolvedValue({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 9,
            total: 0,
            from: 0,
            to: 0,
        });

        render(
            <MemoryRouter initialEntries={['/catalogue?q=Inexistant']}>
                <CataloguePage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Aucune offre trouvée')).toBeTruthy();
            expect(
                screen.getByText(/Aucune offre ne correspond à vos critères de recherche/i)
            ).toBeTruthy();
        });
    });

    it('affiche les contrôles de pagination Laravel lorsque plusieurs pages existent', async () => {
        vi.mocked(offresApi.getCatalogue).mockResolvedValue(mockPaginatedResponse);

        render(
            <MemoryRouter initialEntries={['/catalogue']}>
                <CataloguePage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText(/offre\(s\) disponible\(s\)/i)).toBeTruthy();
            expect(screen.getByRole('navigation')).toBeTruthy();
        });
    });
});
