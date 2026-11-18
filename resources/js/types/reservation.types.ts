/**
 * SkillSwap Campus — Types Réservations & Planning (RG03, RG05, RG06, RG07, RG08, RG09, RG10, RG11, RG12, RG19)
 */

import { User } from './auth.types';
import { Creneau, Offre } from './offre.types';

export type StatutReservation =
    | 'en_attente'
    | 'acceptee'
    | 'refusee'
    | 'annulee'
    | 'realisee'
    | 'en_litige';

export interface Reservation {
    id: number;
    creneau_id: number;
    beneficiaire_id: number;
    statut: StatutReservation;
    cout_credits: number;
    motif_refus?: string | null;
    confirmation_intervenant: boolean;
    confirmation_beneficiaire: boolean;
    date_confirmation_intervenant?: string | null;
    date_confirmation_beneficiaire?: string | null;
    date_demande?: string | null;
    creneau?: Creneau | null;
    offre?: Offre | null;
    beneficiaire?: User | null;
    intervenant?: User | null;
    created_at?: string | null;
}

export interface PlanningActions {
    peut_annuler: boolean;
    peut_confirmer: boolean;
    peut_ouvrir_litige: boolean;
    peut_evaluer: boolean;
}

export interface PlanningItem {
    reservation_id: number;
    statut: StatutReservation;
    cout_credits: number;
    role: 'intervenant' | 'beneficiaire';
    date_demande?: string | null;
    motif_refus?: string | null;
    autre_participant?: {
        id?: number;
        nom?: string;
        prenom?: string;
        filiere?: string | null;
    } | null;
    offre?: {
        id?: number;
        titre?: string;
        competence?: string;
        niveau?: string;
        mode?: string;
        duree?: number;
        categorie?: string | null;
    } | null;
    creneau: {
        id: number;
        date: string;
        heure_debut: string;
        heure_fin: string;
        lieu_public: string;
        lieu_ou_lien_prive?: string | null; // RG19
    };
    actions_possibles: PlanningActions;
}

export interface PlanningResponse {
    total: number;
    planning: PlanningItem[];
}

export interface ReservationListResponse {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    reservations: Reservation[];
}
