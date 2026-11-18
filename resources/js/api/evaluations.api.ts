/**
 * SkillSwap Campus — Module API Évaluations (RG15)
 */

import apiClient from './client';
import { LaravelPaginatedResponse } from '../types/api.types';
import { Evaluation, EvaluationCreationPayload, EvaluationStats } from '../types/evaluation.types';

export const evaluationsApi = {
    /**
     * Dépôt d'une évaluation pour une séance réalisée (RG15).
     * POST /api/evaluations
     */
    async evaluer(payload: EvaluationCreationPayload): Promise<{ message: string; evaluation: Evaluation }> {
        const response = await apiClient.post<{ message: string; evaluation: Evaluation }>('/evaluations', payload);
        return response.data;
    },

    /**
     * Évaluations reçues par l'étudiant connecté.
     * GET /api/evaluations/recues
     */
    async getRecues(page = 1, perPage = 10): Promise<LaravelPaginatedResponse<Evaluation>> {
        const response = await apiClient.get<LaravelPaginatedResponse<Evaluation>>('/evaluations/recues', {
            params: { page, per_page: perPage },
        });
        return response.data;
    },

    /**
     * Évaluations rédigées par l'étudiant connecté.
     * GET /api/evaluations/donnees
     */
    async getDonnees(page = 1, perPage = 10): Promise<LaravelPaginatedResponse<Evaluation>> {
        const response = await apiClient.get<LaravelPaginatedResponse<Evaluation>>('/evaluations/donnees', {
            params: { page, per_page: perPage },
        });
        return response.data;
    },

    /**
     * Statistiques globales d'un étudiant (note moyenne et total).
     * GET /api/users/{id}/evaluations/stats
     */
    async getStats(userId: number): Promise<EvaluationStats> {
        const response = await apiClient.get<EvaluationStats>(`/users/${userId}/evaluations/stats`);
        return response.data;
    },
};

export default evaluationsApi;
