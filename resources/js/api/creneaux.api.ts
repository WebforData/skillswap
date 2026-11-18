/**
 * SkillSwap Campus — Module API Créneaux (RG02, RG07, RG08, RG17)
 */

import apiClient from './client';
import { Creneau, CreneauCreationPayload } from '../types/offre.types';

export const creneauxApi = {
    /**
     * Ajout d'un créneau de disponibilité pour une offre.
     * POST /api/offres/{id}/creneaux
     */
    async ajouterCreneau(
        offreId: number,
        payload: CreneauCreationPayload
    ): Promise<{ message: string; creneau: Creneau }> {
        const response = await apiClient.post<{ message: string; creneau: Creneau }>(
            `/offres/${offreId}/creneaux`,
            payload
        );
        return response.data;
    },

    /**
     * Modification d'un créneau libre (non attribué).
     * PUT /api/creneaux/{id}
     */
    async modifierCreneau(
        id: number,
        payload: Partial<CreneauCreationPayload>
    ): Promise<{ message: string; creneau: Creneau }> {
        const response = await apiClient.put<{ message: string; creneau: Creneau }>(
            `/creneaux/${id}`,
            payload
        );
        return response.data;
    },

    /**
     * Suppression d'un créneau libre.
     * DELETE /api/creneaux/{id}
     */
    async supprimerCreneau(id: number): Promise<{ message: string }> {
        const response = await apiClient.delete<{ message: string }>(`/creneaux/${id}`);
        return response.data;
    },
};

export default creneauxApi;
