/**
 * SkillSwap Campus — Types Évaluations Bilatérales (RG15)
 */

import { User } from './auth.types';

export interface Evaluation {
    id: number;
    reservation_id: number;
    auteur_id: number;
    cible_id: number;
    note: number; // 1 à 5
    commentaire?: string | null;
    auteur?: User | null;
    cible?: User | null;
    created_at?: string | null;
}

export interface EvaluationStats {
    etudiant_id?: number;
    note_moyenne: number | null;
    nombre_evaluations: number;
}

export interface EvaluationCreationPayload {
    reservation_id: number;
    note: number;
    commentaire?: string;
}
