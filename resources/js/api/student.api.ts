import apiClient from './client';
import { User } from '../types/auth.types';
import { Categorie, Offre } from '../types/offre.types';
import { LaravelPaginatedResponse } from '../types/api.types';
import { Litige } from '../types/admin.types';
export interface Movement {
    id: number;
    quantite: number;
    type: string;
    date_mouvement: string;
    reservation_id: number | null;
    reservation?: { creneau?: { offre?: { titre: string } } };
}
export interface PersonalNotification {
    id: number;
    title: string;
    message: string;
    is_read: boolean;
    reservation_id: number | null;
    created_at: string;
}
export const studentApi = {
    categories: async () =>
        (await apiClient.get<{ categories: Categorie[] }>('/categories')).data.categories,
    updateProfile: async (data: Partial<User>) =>
        (await apiClient.put<{ user: User }>('/auth/profile', data)).data,
    history: async (page = 1) =>
        (
            await apiClient.get<LaravelPaginatedResponse<Movement>>('/wallet/history', {
                params: { page },
            })
        ).data,
    suggestions: async () => (await apiClient.get<{ offres: Offre[] }>('/suggestions')).data.offres,
    notifications: async (page = 1) =>
        (
            await apiClient.get<LaravelPaginatedResponse<PersonalNotification>>('/notifications', {
                params: { page },
            })
        ).data,
    readNotification: async (id: number) => apiClient.patch(`/notifications/${id}/read`),
    disputes: async () => (await apiClient.get<{ litiges: Litige[] }>('/mes-litiges')).data.litiges,
    statement: async (id: number, message: string) =>
        apiClient.post(`/litiges/${id}/statements`, { message }),
};
