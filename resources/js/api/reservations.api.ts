/**
 * SkillSwap Campus — Module API Réservations (RG03, RG05, RG06, RG07, RG09, RG10, RG11, RG12)
 */

import apiClient from './client';
import { Reservation, ReservationListResponse } from '../types/reservation.types';
import { Litige } from '../types/admin.types';

export const reservationsApi = {
    /**
     * Liste paginée des réservations de l'utilisateur connecté.
     * GET /api/reservations
     */
    async getMesReservations(params: {
        role?: 'tous' | 'beneficiaire' | 'intervenant';
        statut?: string;
        page?: number;
        per_page?: number;
    } = {}): Promise<ReservationListResponse> {
        const response = await apiClient.get<ReservationListResponse>('/reservations', { params });
        return response.data;
    },

    /**
     * Consultation d'une réservation détaillée avec ses coordonnées protégées (RG19).
     * GET /api/reservations/{id}
     */
    async getReservation(id: number): Promise<{ reservation: Reservation }> {
        const response = await apiClient.get<{ reservation: Reservation }>(`/reservations/${id}`);
        return response.data;
    },

    /**
     * Formule une demande de réservation en attente.
     * POST /api/reservations
     */
    async demanderReservation(creneauId: number): Promise<{ message: string; reservation: Reservation }> {
        const response = await apiClient.post<{ message: string; reservation: Reservation }>('/reservations', {
            creneau_id: creneauId,
        });
        return response.data;
    },

    /**
     * Acceptation d'une réservation par l'intervenant (RG06, RG07).
     * POST /api/reservations/{id}/accepter
     */
    async accepter(id: number): Promise<{ message: string; reservation: Reservation }> {
        const response = await apiClient.post<{ message: string; reservation: Reservation }>(
            `/reservations/${id}/accepter`
        );
        return response.data;
    },

    /**
     * Refus d'une réservation par l'intervenant avec motif (RG09).
     * POST /api/reservations/{id}/refuser
     */
    async refuser(id: number, motif: string): Promise<{ message: string; reservation: Reservation }> {
        const response = await apiClient.post<{ message: string; reservation: Reservation }>(
            `/reservations/${id}/refuser`,
            { motif }
        );
        return response.data;
    },

    /**
     * Retrait par le bénéficiaire tant que la réservation est en attente.
     * POST /api/reservations/{id}/retirer
     */
    async retirer(id: number): Promise<{ message: string }> {
        const response = await apiClient.post<{ message: string }>(`/reservations/${id}/retirer`);
        return response.data;
    },

    /**
     * Annulation pré-séance par l'un des participants (RG10).
     * POST /api/reservations/{id}/annuler
     */
    async annuler(id: number, motif: string): Promise<{ message: string; reservation: Reservation }> {
        const response = await apiClient.post<{ message: string; reservation: Reservation }>(
            `/reservations/${id}/annuler`,
            { motif }
        );
        return response.data;
    },

    /**
     * Confirmation post-séance (RG11, transfert des crédits à la double confirmation).
     * POST /api/reservations/{id}/confirmer
     */
    async confirmer(id: number): Promise<{ message: string; reservation: Reservation; cloturee: boolean }> {
        const response = await apiClient.post<{ message: string; reservation: Reservation; cloturee: boolean }>(
            `/reservations/${id}/confirmer`
        );
        return response.data;
    },

    /**
     * Déclaration d'un litige sur une séance (RG12).
     * POST /api/reservations/{id}/litige
     */
    async declarerLitige(id: number, description: string): Promise<{ message: string; litige: Litige }> {
        const response = await apiClient.post<{ message: string; litige: Litige }>(
            `/reservations/${id}/litige`,
            { description }
        );
        return response.data;
    },
};

export default reservationsApi;
