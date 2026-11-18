/**
 * SkillSwap Campus — Garde de Route Utilisateur Authentifié & Actif (RG18)
 */

import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { Loader2, ShieldAlert } from 'lucide-react';

interface ProtectedRouteProps {
    children?: React.ReactNode;
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
    const { isAuthenticated, isLoading, user, logout } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return (
            <div className="min-h-screen flex flex-col items-center justify-center bg-slate-50 text-slate-700">
                <Loader2 className="w-10 h-10 animate-spin text-indigo-600 mb-4" />
                <p className="text-sm font-medium text-slate-600">Vérification de la session en cours...</p>
            </div>
        );
    }

    if (!isAuthenticated || !user) {
        return <Navigate to="/connexion" state={{ from: location }} replace />;
    }

    // RG18 : Compte désactivé formellement bloqué
    if (user.statut === 'desactive') {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-red-200 p-8 text-center">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-red-50 text-red-600 mb-6">
                        <ShieldAlert className="w-9 h-9" />
                    </div>
                    <h2 className="text-xl font-bold text-slate-900 mb-2">Compte Désactivé</h2>
                    <p className="text-sm text-slate-600 mb-6 leading-relaxed">
                        Votre compte a été désactivé par un administrateur conformément aux règles de la plateforme (RG18).
                        Veuillez contacter le support du campus pour plus d'informations.
                    </p>
                    <button
                        onClick={() => logout()}
                        className="w-full px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-colors"
                    >
                        Retourner à l'accueil
                    </button>
                </div>
            </div>
        );
    }

    return children ? <>{children}</> : <Outlet />;
};

export default ProtectedRoute;
