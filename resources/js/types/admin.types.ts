/**
 * SkillSwap Campus — Types Back-Office Administrateur (RG13, RG14, RG17, RG18)
 */

import { User } from './auth.types';
import { Reservation } from './reservation.types';
import { Offre } from './offre.types';

export type LitigeDecision = 'transfert_intervenant' | 'restitution_beneficiaire';
export type LitigeStatut = 'ouvert' | 'resolu';

export interface Litige {
    id: number;
    reservation_id: number;
    declarant_id: number;
    admin_id?: number | null;
    description: string;
    statut: LitigeStatut;
    decision?: LitigeDecision | null;
    motif_decision?: string | null;
    date_resolution?: string | null;
    declarant?: User | null;
    administrateur?: User | null;
    reservation?: Reservation | null;
    created_at?: string | null;
}

export interface AdminStats {
    utilisateurs: {
        total: number;
        etudiants: number;
        administrateurs: number;
        actifs: number;
        desactives: number;
    };
    offres: {
        total: number;
        publiees: number;
        brouillons: number;
        archivees: number;
    };
    reservations: {
        total: number;
        en_attente: number;
        acceptees: number;
        realisees: number;
        annulees: number;
        en_litige: number;
        refusees: number;
    };
    activite_financiere: {
        seances_realisees: number;
        credits_transferes: number;
        credits_sequestres: number;
        litiges_ouverts: number;
        litiges_resolus: number;
    };
    evolution_mensuelle: Array<{
        mois: string;
        total: number;
    }>;
}

export interface AdminUserFilter {
    q?: string;
    search?: string;
    role?: string;
    statut?: string;
    per_page?: number;
    page?: number;
}

export interface AdminOffreFilter {
    q?: string;
    search?: string;
    statut?: string;
    categorie_id?: number | string;
    niveau?: string;
    mode?: string;
    per_page?: number;
    page?: number;
}

export interface AdminLitigeFilter {
    statut?: string;
    per_page?: number;
    page?: number;
}

export interface AdminUsersResponse {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    users: User[];
}

export interface AdminOffresResponse {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    offres: Offre[];
}

export interface AdminLitigesResponse {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    litiges: Litige[];
}

export interface LitigeArbitragePayload {
    decision: LitigeDecision;
    motif: string;
}
