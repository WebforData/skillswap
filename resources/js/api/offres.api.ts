/**
 * SkillSwap Campus — Module API Offres (RG04, RG17)
 */

import apiClient from './client';
import { LaravelPaginatedResponse } from '../types/api.types';
import { Offre, OffreFiltres } from '../types/offre.types';

export const offresApi = {
    /**
     * Recherche dans le catalogue public d'offres.
     * GET /api/offres
     */
    async getCatalogue(filtres: OffreFiltres = {}): Promise<LaravelPaginatedResponse<Offre>> {
        const params: Record<string, any> = { ...filtres };
        if (filtres.search && !filtres.q) {
            params.q = filtres.search;
        }
        // Nettoyer les paramètres vides
        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === null || params[key] === undefined) {
                delete params[key];
            }
        });
        const response = await apiClient.get<LaravelPaginatedResponse<Offre>>('/offres', {
            params,
        });
        return response.data;
    },

    /**
     * Consultation d'une offre détaillée avec ses créneaux disponibles.
     * GET /api/offres/{id}
     */
    async getOffre(id: number): Promise<{ offre: Offre }> {
        const response = await apiClient.get<{ offre: Offre }>(`/offres/${id}`);
        return response.data;
    },

    /**
     * Liste des offres créées par l'étudiant connecté.
     * GET /api/offres/mes-offres
     */
    async getMesOffres(page = 1, perPage = 10): Promise<LaravelPaginatedResponse<Offre>> {
        const response = await apiClient.get<LaravelPaginatedResponse<Offre>>('/offres/mes-offres', {
            params: { page, per_page: perPage },
        });
        return response.data;
    },

    /**
     * Création d'une offre (RG02, RG04).
     * POST /api/offres
     */
    async creerOffre(payload: Partial<Offre>): Promise<{ message: string; offre: Offre }> {
        const response = await apiClient.post<{ message: string; offre: Offre }>('/offres', payload);
        return response.data;
    },

    /**
     * Modification d'une offre existante.
     * PUT /api/offres/{id}
     */
    async modifierOffre(id: number, payload: Partial<Offre>): Promise<{ message: string; offre: Offre }> {
        const response = await apiClient.put<{ message: string; offre: Offre }>(`/offres/${id}`, payload);
        return response.data;
    },

    /**
     * Publication d'une offre.
     * POST /api/offres/{id}/publier
     */
    async publierOffre(id: number): Promise<{ message: string; offre: Offre }> {
        const response = await apiClient.post<{ message: string; offre: Offre }>(`/offres/${id}/publier`);
        return response.data;
    },

    /**
     * Repassage en brouillon d'une offre.
     * POST /api/offres/{id}/brouillon
     */
    async passerEnBrouillon(id: number): Promise<{ message: string; offre: Offre }> {
        const response = await apiClient.post<{ message: string; offre: Offre }>(`/offres/${id}/brouillon`);
        return response.data;
    },

    /**
     * Archivage d'une offre (RG17).
     * POST /api/offres/{id}/archiver
     */
    async archiverOffre(id: number): Promise<{ message: string; offre: Offre }> {
        const response = await apiClient.post<{ message: string; offre: Offre }>(`/offres/${id}/archiver`);
        return response.data;
    },
};

export default offresApi;
