/**
 * Tests Unitaires Frontend — Garde de Route Administrateur (AdminRoute)
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { AdminRoute } from '../../routes/AdminRoute';
import { AuthContext } from '../../contexts/AuthContext';
import { User } from '../../types/auth.types';

const createMockAuthContext = (overrides: Partial<{
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
}> = {}) => ({
    user: overrides.user !== undefined ? overrides.user : null,
    token: overrides.isAuthenticated ? 'fake_token' : null,
    isAuthenticated: overrides.isAuthenticated ?? false,
    isLoading: overrides.isLoading ?? false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: vi.fn(),
    clearError: vi.fn(),
});

describe('AdminRoute', () => {
    it('autorise l’accès et rend le composant enfant si l’utilisateur est un administrateur authentifié', () => {
        const adminUser: User = {
            id: 1,
            nom: 'Admin',
            prenom: 'Chef',
            email: 'admin@univ.fr',
            role: 'administrateur',
            statut: 'actif',
            filiere: 'Administration',
        };

        render(
            <MemoryRouter initialEntries={['/admin']}>
                <AuthContext.Provider value={createMockAuthContext({ user: adminUser, isAuthenticated: true })}>
                    <Routes>
                        <Route
                            path="/admin"
                            element={
                                <AdminRoute>
                                    <div>Section Admin Protégée</div>
                                </AdminRoute>
                            }
                        />
                    </Routes>
                </AuthContext.Provider>
            </MemoryRouter>
        );

        expect(screen.getByText('Section Admin Protégée')).toBeTruthy();
    });

    it('bloque l’accès et affiche le message de refus si l’utilisateur est un étudiant connecté', () => {
        const studentUser: User = {
            id: 2,
            nom: 'Élève',
            prenom: 'Paul',
            email: 'paul@univ.fr',
            role: 'etudiant',
            statut: 'actif',
            filiere: 'Informatique',
        };

        render(
            <MemoryRouter initialEntries={['/admin']}>
                <AuthContext.Provider value={createMockAuthContext({ user: studentUser, isAuthenticated: true })}>
                    <Routes>
                        <Route
                            path="/admin"
                            element={
                                <AdminRoute>
                                    <div>Section Admin Protégée</div>
                                </AdminRoute>
                            }
                        />
                    </Routes>
                </AuthContext.Provider>
            </MemoryRouter>
        );

        expect(screen.queryByText('Section Admin Protégée')).toBeNull();
        expect(screen.getByText('Accès Administrateur Requis')).toBeTruthy();
        expect(screen.getByText(/strictement réservée aux administrateurs/i)).toBeTruthy();
    });

    it('redirige vers /connexion si l’utilisateur n’est pas authentifié', () => {
        render(
            <MemoryRouter initialEntries={['/admin']}>
                <AuthContext.Provider value={createMockAuthContext({ user: null, isAuthenticated: false })}>
                    <Routes>
                        <Route
                            path="/admin"
                            element={
                                <AdminRoute>
                                    <div>Section Admin Protégée</div>
                                </AdminRoute>
                            }
                        />
                        <Route path="/connexion" element={<div>Page de Connexion</div>} />
                    </Routes>
                </AuthContext.Provider>
            </MemoryRouter>
        );

        expect(screen.queryByText('Section Admin Protégée')).toBeNull();
        expect(screen.getByText('Page de Connexion')).toBeTruthy();
    });
});
