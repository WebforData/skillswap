/**
 * SkillSwap Campus — Module API Back-Office Administrateur (RG13, RG14, RG17, RG18)
 */

import apiClient from './client';
import { User } from '../types/auth.types';
import { Categorie, Offre } from '../types/offre.types';
import {
    AdminLitigeFilter,
    AdminLitigesResponse,
    AdminOffreFilter,
    AdminOffresResponse,
    AdminStats,
    AdminUserFilter,
    AdminUsersResponse,
    Litige,
    LitigeArbitragePayload,
} from '../types/admin.types';

export const adminApi = {
    /**
     * Indicateurs globaux et statistiques de la plateforme.
     * GET /api/admin/stats
     */
    async getStats(): Promise<{ stats: AdminStats }> {
        const response = await apiClient.get<{ stats: AdminStats }>('/admin/stats');
        return response.data;
    },

    /**
     * Liste paginée des utilisateurs.
     * GET /api/admin/users
     */
    async getUsers(params: AdminUserFilter = {}): Promise<AdminUsersResponse> {
        const cleanParams: Record<string, unknown> = { ...params };
        if (cleanParams.search && !cleanParams.q) {
            cleanParams.q = cleanParams.search;
            delete cleanParams.search;
        }
        const response = await apiClient.get<AdminUsersResponse>('/admin/users', { params: cleanParams });
        return response.data;
    },

    /**
     * Détails complets d'un utilisateur.
     * GET /api/admin/users/{id}
     */
    async getUser(id: number): Promise<{ user: User }> {
        const response = await apiClient.get<{ user: User }>(`/admin/users/${id}`);
        return response.data;
    },

    /**
     * Réactivation d'un compte étudiant (RG18).
     * PATCH /api/admin/users/{id}/activer
     */
    async activerUser(id: number): Promise<{ message: string; user: User }> {
        const response = await apiClient.patch<{ message: string; user: User }>(`/admin/users/${id}/activer`);
        return response.data;
    },

    /**
     * Désactivation d'un compte étudiant (RG18).
     * PATCH /api/admin/users/{id}/desactiver
     */
    async desactiverUser(id: number): Promise<{ message: string; user: User }> {
        const response = await apiClient.patch<{ message: string; user: User }>(`/admin/users/${id}/desactiver`);
        return response.data;
    },

    /**
     * Liste des catégories avec comptage des offres.
     * GET /api/admin/categories
     */
    async getCategories(): Promise<{ total: number; categories: Categorie[] }> {
        const response = await apiClient.get<{ total: number; categories: Categorie[] }>('/admin/categories');
        return response.data;
    },

    /**
     * Création d'une catégorie.
     * POST /api/admin/categories
     */
    async creerCategorie(nom: string): Promise<{ message: string; categorie: Categorie }> {
        const response = await apiClient.post<{ message: string; categorie: Categorie }>('/admin/categories', { nom });
        return response.data;
    },

    /**
     * Renommage d'une catégorie.
     * PUT /api/admin/categories/{id}
     */
    async modifierCategorie(id: number, nom: string): Promise<{ message: string; categorie: Categorie }> {
        const response = await apiClient.put<{ message: string; categorie: Categorie }>(`/admin/categories/${id}`, { nom });
        return response.data;
    },

    /**
     * Suppression d'une catégorie (bloquée avec 409 si liée à des offres).
     * DELETE /api/admin/categories/{id}
     */
    async supprimerCategorie(id: number): Promise<{ message: string }> {
        const response = await apiClient.delete<{ message: string }>(`/admin/categories/${id}`);
        return response.data;
    },

    /**
     * Liste paginée des offres pour modération.
     * GET /api/admin/offres
     */
    async getOffres(params: AdminOffreFilter = {}): Promise<AdminOffresResponse> {
        const cleanParams: Record<string, unknown> = { ...params };
        if (cleanParams.search && !cleanParams.q) {
            cleanParams.q = cleanParams.search;
            delete cleanParams.search;
        }
        const response = await apiClient.get<AdminOffresResponse>('/admin/offres', { params: cleanParams });
        return response.data;
    },

    /**
     * Détails exhaustifs d'une offre pour modération.
     * GET /api/admin/offres/{id}
     */
    async getOffre(id: number): Promise<{ offre: Offre }> {
        const response = await apiClient.get<{ offre: Offre }>(`/admin/offres/${id}`);
        return response.data;
    },

    /**
     * Archivage administratif d'une offre (RG17).
     * PATCH /api/admin/offres/{id}/archiver
     */
    async archiverOffre(id: number): Promise<{ message: string; offre: Offre }> {
        const response = await apiClient.patch<{ message: string; offre: Offre }>(`/admin/offres/${id}/archiver`);
        return response.data;
    },

    /**
     * Liste paginée des litiges pour arbitrage administratif (RG13).
     * GET /api/litiges
     */
    async getLitiges(params: AdminLitigeFilter = {}): Promise<AdminLitigesResponse> {
        const response = await apiClient.get<AdminLitigesResponse>('/litiges', { params });
        return response.data;
    },

    /**
     * Détail d'un litige pour arbitrage administratif.
     * GET /api/litiges/{id}
     */
    async getLitige(id: number): Promise<{ litige: Litige }> {
        const response = await apiClient.get<{ litige: Litige }>(`/litiges/${id}`);
        return response.data;
    },

    /**
     * Arbitrage définitif d'un litige (RG13, RG14).
     * POST /api/litiges/{id}/arbitrer
     */
    async arbitrerLitige(id: number, payload: LitigeArbitragePayload): Promise<{ message: string; litige: Litige }> {
        const response = await apiClient.post<{ message: string; litige: Litige }>(`/litiges/${id}/arbitrer`, payload);
        return response.data;
    },
};

export default adminApi;
