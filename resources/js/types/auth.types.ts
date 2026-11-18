/**
 * SkillSwap Campus — Types d'Authentification & Utilisateur
 */

export type UserRole = 'etudiant' | 'administrateur';
export type UserStatus = 'actif' | 'desactive';

export interface Portefeuille {
    id?: number;
    etudiant_id?: number;
    solde_disponible: number;
    credits_bloques: number;
    solde_total: number;
    updated_at?: string | null;
}

export interface User {
    id: number;
    nom: string;
    prenom: string;
    nom_complet?: string;
    email: string;
    role: UserRole;
    statut: UserStatus;
    filiere: string | null;
    competences_proposees?: string | null;
    competences_recherchees?: string | null;
    portefeuille?: Portefeuille | null;
    solde_disponible?: number;
    credits_bloques?: number;
    created_at?: string | null;
}

export interface AuthResponse {
    message: string;
    user: User;
    token: string;
    token_type: string;
}

export interface RegisterCredentials {
    nom: string;
    prenom: string;
    email: string;
    password: string;
    password_confirmation?: string;
    filiere?: string | null;
    competences_proposees?: string | null;
    competences_recherchees?: string | null;
}

export interface LoginCredentials {
    email: string;
    password: string;
}

export interface AuthState {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
}
