/**
 * Tests Unitaires & Intégration Frontend — Back-Office : Dashboard Administrateur
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AdminDashboardPage } from '../../pages/admin/AdminDashboardPage';
import { adminApi } from '../../api/admin.api';
import { AdminStats } from '../../types/admin.types';

vi.mock('../../api/admin.api', () => {
    const api = {
        getStats: vi.fn(),
    };
    return {
        adminApi: api,
        default: api,
    };
});

const mockStats: AdminStats = {
    utilisateurs: {
        total: 120,
        etudiants: 115,
        administrateurs: 5,
        actifs: 110,
        desactives: 10,
    },
    offres: {
        total: 64,
        publiees: 45,
        brouillons: 12,
        archivees: 7,
    },
    reservations: {
        total: 80,
        en_attente: 15,
        acceptees: 25,
        realisees: 30,
        annulees: 6,
        en_litige: 2,
        refusees: 2,
    },
    activite_financiere: {
        seances_realisees: 30,
        credits_transferes: 85,
        credits_sequestres: 18,
        litiges_ouverts: 2,
        litiges_resolus: 4,
    },
    evolution_mensuelle: [
        { mois: '2026-06', total: 10 },
        { mois: '2026-07', total: 14 },
        { mois: '2026-08', total: 8 },
        { mois: '2026-09', total: 22 },
        { mois: '2026-10', total: 18 },
        { mois: '2026-11', total: 8 },
    ],
};

describe('AdminDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(adminApi.getStats).mockResolvedValue({ stats: mockStats });
    });

    it('charge et affiche les métriques clés de pilotage et l’intégrité financière (RG16)', async () => {
        render(
            <MemoryRouter>
                <AdminDashboardPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Indicateurs & Pilotage Campus')).toBeTruthy();
            expect(screen.getByText(/Intégrité Financière & Règle RG16/i)).toBeTruthy();
            // Crédits transférés
            expect(screen.getByText(/85/)).toBeTruthy();
            expect(screen.getByText('Crédits Transférés')).toBeTruthy();
            // Crédits séquestrés
            expect(screen.getByText('Crédits Bloqués (Séquestre)')).toBeTruthy();
            expect(screen.getByText('Litiges Ouverts')).toBeTruthy();
            expect(screen.getByText('Séances Terminées')).toBeTruthy();
        });
    });

    it('affiche la répartition des utilisateurs et des offres', async () => {
        render(
            <MemoryRouter>
                <AdminDashboardPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Communauté & Comptes')).toBeTruthy();
            expect(screen.getByText('120 au total')).toBeTruthy();
            expect(screen.getByText('Offres de Compétences')).toBeTruthy();
            expect(screen.getByText('64 au total')).toBeTruthy();
            expect(screen.getByText('Cycle des Réservations')).toBeTruthy();
        });
    });

    it('affiche le graphique d’évolution mensuelle sur 6 mois', async () => {
        render(
            <MemoryRouter>
                <AdminDashboardPage />
            </MemoryRouter>
        );

        await waitFor(() => {
            expect(screen.getByText('Évolution Mensuelle des Demandes')).toBeTruthy();
            expect(screen.getByText('2026-06')).toBeTruthy();
            expect(screen.getByText('2026-11')).toBeTruthy();
        });
    });
});
