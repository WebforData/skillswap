/**
 * SkillSwap Campus — Module API Planning Consolidé (RG08, RG19)
 */

import apiClient from './client';
import { PlanningResponse } from '../types/reservation.types';

export const planningApi = {
    /**
     * Récupère le planning et calendrier consolidé de l'étudiant.
     * GET /api/planning
     */
    async getPlanning(params: {
        role?: 'tous' | 'intervenant' | 'beneficiaire';
        a_venir?: boolean;
        historique?: boolean;
        filtre?: 'a_venir' | 'historique';
        statut?: string;
        date_debut?: string;
        date_fin?: string;
    } = {}): Promise<PlanningResponse> {
        const response = await apiClient.get<PlanningResponse>('/planning', { params });
        return response.data;
    },
};

export default planningApi;
