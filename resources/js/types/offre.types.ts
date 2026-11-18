/**
 * SkillSwap Campus — Types Offres & Créneaux (RG02, RG04, RG07, RG08, RG17, RG19)
 */

import { User } from './auth.types';

export type NiveauOffre = 'debutant' | 'intermediaire' | 'avance';
export type ModeOffre = 'presentiel' | 'distanciel';
export type StatutOffre = 'brouillon' | 'publiee' | 'archivee';

export interface Categorie {
    id: number;
    nom: string;
    offres_count?: number;
    created_at?: string | null;
}

export interface Creneau {
    id: number;
    offre_id: number;
    date: string;
    heure_debut: string;
    heure_fin: string;
    lieu_public: string;
    lieu_ou_lien_prive?: string | null; // RG19 : visible uniquement si autorisé
    est_disponible?: boolean;
    offre?: Offre | null;
    created_at?: string | null;
}

export interface Offre {
    id: number;
    etudiant_id: number;
    categorie_id: number;
    titre: string;
    competence: string;
    description: string;
    niveau: NiveauOffre;
    duree: number; // 1 ou 2 heures (RG02)
    mode: ModeOffre;
    statut?: StatutOffre;
    categorie?: Categorie | null;
    intervenant?: User | null;
    creneaux?: Creneau[];
    created_at?: string | null;
}

export interface OffreFiltres {
    q?: string;
    search?: string;
    categorie_id?: number | string;
    mode?: ModeOffre | '';
    niveau?: NiveauOffre | '';
    duree?: number | string;
    per_page?: number;
    page?: number;
}

export interface CreneauCreationPayload {
    date: string;
    heure_debut: string;
    heure_fin: string;
    lieu_public: string;
    lien_ou_lieu_prive: string;
}
