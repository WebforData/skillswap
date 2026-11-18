/**
 * SkillSwap Campus — Garde de Route Back-Office Administrateur
 */

import React from 'react';
import { Navigate, Outlet, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { Loader2, ShieldX } from 'lucide-react';

interface AdminRouteProps {
    children?: React.ReactNode;
}

export const AdminRoute: React.FC<AdminRouteProps> = ({ children }) => {
    const { isAuthenticated, isLoading, user } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return (
            <div className="min-h-screen flex flex-col items-center justify-center bg-slate-50 text-slate-700">
                <Loader2 className="w-10 h-10 animate-spin text-indigo-600 mb-4" />
                <p className="text-sm font-medium text-slate-600">Vérification des droits d'administration...</p>
            </div>
        );
    }

    if (!isAuthenticated || !user) {
        return <Navigate to="/connexion" state={{ from: location }} replace />;
    }

    // Contrôle du rôle administrateur côté frontend (complété obligatoirement par les contrôles Laravel en backend)
    if (user.role !== 'administrateur') {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-amber-200 p-8 text-center">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 mb-6">
                        <ShieldX className="w-9 h-9" />
                    </div>
                    <h2 className="text-xl font-bold text-slate-900 mb-2">Accès Administrateur Requis</h2>
                    <p className="text-sm text-slate-600 mb-6 leading-relaxed">
                        Cette section est strictement réservée aux administrateurs de la plateforme SkillSwap Campus.
                    </p>
                    <Link
                        to="/app"
                        className="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white rounded-xl font-medium text-sm hover:bg-indigo-700 transition shadow-sm"
                    >
                        Retourner à l'espace étudiant
                    </Link>
                </div>
            </div>
        );
    }

    return children ? <>{children}</> : <Outlet />;
};

export default AdminRoute;
