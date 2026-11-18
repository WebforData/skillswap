/**
 * Tests Unitaires & Intégration Frontend — Mes Offres & Créneaux (MesOffresPage)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { MesOffresPage } from '../../pages/student/MesOffresPage';
import { NotificationContext, NotificationContextType } from '../../contexts/NotificationContext';
import { offresApi } from '../../api/offres.api';
import { creneauxApi } from '../../api/creneaux.api';
import { Offre } from '../../types/offre.types';

vi.mock('../../api/offres.api', () => ({
    offresApi: {
        getMesOffres: vi.fn(),
        getOffre: vi.fn(),
        creerOffre: vi.fn(),
        modifierOffre: vi.fn(),
        publierOffre: vi.fn(),
        passerEnBrouillon: vi.fn(),
        archiverOffre: vi.fn(),
    },
}));

vi.mock('../../api/creneaux.api', () => ({
    creneauxApi: {
        ajouterCreneau: vi.fn(),
        modifierCreneau: vi.fn(),
        supprimerCreneau: vi.fn(),
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

const mockMyOffers: Offre[] = [
    {
        id: 10,
        etudiant_id: 1,
        categorie_id: 1,
        titre: 'Cours avancé de React et Tailwind',
        competence: 'React 19',
        description: 'Formation approfondie aux composants réutilisables.',
        niveau: 'intermediaire',
        duree: 2,
        mode: 'distanciel',
        statut: 'publiee',
        categorie: { id: 1, nom: 'Informatique & Programmation' },
        creneaux: [
            {
                id: 101,
                offre_id: 10,
                date: '2026-11-28',
                heure_debut: '14:00:00',
                heure_fin: '16:00:00',
                lieu_public: 'Visioconférence Google Meet',
                lieu_ou_lien_prive: 'https://meet.google.com/abc-defg-hij',
            },
        ],
    },
    {
        id: 11,
        etudiant_id: 1,
        categorie_id: 2,
        titre: 'Conversation en Anglais B2',
        competence: 'Anglais',
        description: 'Pratique orale pour préparer les entretiens.',
        niveau: 'debutant',
        duree: 1,
        mode: 'presentiel',
        statut: 'brouillon',
        categorie: { id: 2, nom: 'Langues & Communication' },
        creneaux: [],
    },
];

const renderMesOffresPage = () => {
    return render(
        <MemoryRouter initialEntries={['/app/mes-offres']}>
            <NotificationContext.Provider value={mockNotificationContext}>
                <MesOffresPage />
            </NotificationContext.Provider>
        </MemoryRouter>
    );
};

describe('MesOffresPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(offresApi.getMesOffres).mockResolvedValue({
            data: mockMyOffers,
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 2,
            from: 1,
            to: 2,
        });
    });

    it('affiche la liste des offres personnelles et permet le filtrage par statut', async () => {
        renderMesOffresPage();

        await waitFor(() => {
            expect(screen.getByText('Cours avancé de React et Tailwind')).toBeTruthy();
            expect(screen.getByText('Conversation en Anglais B2')).toBeTruthy();
            expect(screen.getByText('2 crédits (RG02)')).toBeTruthy();
        });

        // Filtrer par "Brouillons"
        const brouillonFilterBtn = screen.getByRole('button', { name: /^Brouillons$/i });
        fireEvent.click(brouillonFilterBtn);

        await waitFor(() => {
            expect(screen.queryByText('Cours avancé de React et Tailwind')).toBeNull();
            expect(screen.getByText('Conversation en Anglais B2')).toBeTruthy();
        });
    });

    it("permet de créer une nouvelle offre et l'enregistre via offresApi.creerOffre", async () => {
        vi.mocked(offresApi.creerOffre).mockResolvedValue({
            message: 'Offre créée',
            offre: {
                id: 12,
                etudiant_id: 1,
                categorie_id: 1,
                titre: 'Nouveau cours de TypeScript',
                competence: 'TypeScript',
                description: 'Description détaillée avec plus de vingt caractères indispensables.',
                niveau: 'debutant',
                duree: 1,
                mode: 'distanciel',
                statut: 'brouillon',
            },
        });

        renderMesOffresPage();

        await waitFor(() => {
            expect(screen.getByText('Cours avancé de React et Tailwind')).toBeTruthy();
        });

        // Ouvrir modale de création
        const createBtn = screen.getByRole('button', { name: /Proposer une compétence/i });
        fireEvent.click(createBtn);

        await waitFor(() => {
            expect(screen.getByText('Proposer une nouvelle compétence')).toBeTruthy();
        });

        fireEvent.change(screen.getByLabelText(/Titre de l'offre/i), {
            target: { value: 'Nouveau cours de TypeScript' },
        });
        fireEvent.change(screen.getByLabelText(/Compétence principale enseignée/i), {
            target: { value: 'TypeScript' },
        });
        fireEvent.change(screen.getByLabelText(/Description détaillée du cours/i), {
            target: { value: 'Description détaillée avec plus de vingt caractères indispensables.' },
        });

        const submitBtn = screen.getByRole('button', { name: /Créer l'offre/i });
        fireEvent.click(submitBtn);

        await waitFor(() => {
            expect(offresApi.creerOffre).toHaveBeenCalledWith(
                expect.objectContaining({
                    titre: 'Nouveau cours de TypeScript',
                    competence: 'TypeScript',
                    duree: 1,
                    statut: 'brouillon',
                })
            );
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Nouvelle offre créée')
            );
        });
    });

    it("permet d'archiver une offre avec avertissement des règles RG17", async () => {
        vi.mocked(offresApi.archiverOffre).mockResolvedValue({
            message: 'Offre archivée',
            offre: { ...mockMyOffers[0], statut: 'archivee' },
        });

        renderMesOffresPage();

        await waitFor(() => {
            expect(screen.getByText('Cours avancé de React et Tailwind')).toBeTruthy();
        });

        // Cliquer sur le bouton Archiver de la première offre
        const archiveButtons = screen.getAllByRole('button', { name: /^Archiver$/i });
        fireEvent.click(archiveButtons[0]);

        await waitFor(() => {
            expect(screen.getByText(/Confirmer l'archivage de l'offre \(RG17\)/i)).toBeTruthy();
            expect(screen.getByText(/Toutes les demandes actuellement/i)).toBeTruthy();
        });

        const confirmArchiveBtn = screen.getByRole('button', { name: /Confirmer l'archivage/i });
        fireEvent.click(confirmArchiveBtn);

        await waitFor(() => {
            expect(offresApi.archiverOffre).toHaveBeenCalledWith(10);
            expect(mockNotificationContext.success).toHaveBeenCalledWith(
                expect.stringContaining('Offre archivée avec succès (RG17)')
            );
        });
    });

    it('ouvre la modale de gestion des créneaux et permet d’ajouter un créneau', async () => {
        vi.mocked(creneauxApi.ajouterCreneau).mockResolvedValue({
            message: 'Créneau ajouté',
            creneau: {
                id: 102,
                offre_id: 10,
                date: '2026-11-30',
                heure_debut: '14:00',
                heure_fin: '16:00',
                lieu_public: 'Campus Universitaire',
                lieu_ou_lien_prive: 'Salle B12',
            },
        });

        vi.mocked(offresApi.getOffre).mockResolvedValue({
            offre: {
                ...mockMyOffers[0],
                creneaux: [
                    mockMyOffers[0].creneaux![0],
                    {
                        id: 102,
                        offre_id: 10,
                        date: '2026-11-30',
                        heure_debut: '14:00:00',
                        heure_fin: '16:00:00',
                        lieu_public: 'Campus Universitaire',
                        lieu_ou_lien_prive: 'Salle B12',
                    },
                ],
            },
        });

        renderMesOffresPage();

        await waitFor(() => {
            expect(screen.getByText('Cours avancé de React et Tailwind')).toBeTruthy();
        });

        // Cliquer sur "Gérer les créneaux"
        const manageSlotsBtn = screen.getAllByRole('button', { name: /Gérer les créneaux/i })[0];
        fireEvent.click(manageSlotsBtn);

        await waitFor(() => {
            expect(screen.getByText(/Gestion des créneaux — Cours avancé de React/i)).toBeTruthy();
            expect(screen.getByText(/Protection RG19/i)).toBeTruthy();
        });

        // Remplir le formulaire d'ajout
        fireEvent.change(screen.getByLabelText(/Date de la séance/i), {
            target: { value: '2026-11-30' },
        });
        fireEvent.change(screen.getByLabelText(/Heure de début/i), {
            target: { value: '14:00' },
        });
        fireEvent.change(screen.getByLabelText(/Heure de fin/i), {
            target: { value: '16:00' },
        });

        const saveSlotBtn = screen.getByRole('button', { name: /Enregistrer ce créneau/i });
        fireEvent.click(saveSlotBtn);

        await waitFor(() => {
            expect(creneauxApi.ajouterCreneau).toHaveBeenCalledWith(
                10,
                expect.objectContaining({
                    date: '2026-11-30',
                    heure_debut: '14:00',
                    heure_fin: '16:00',
                })
            );
        });
    });
});
