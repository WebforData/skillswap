/**
 * SkillSwap Campus — Barre de Navigation Principale Adaptative
 */

import React, { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import {
    GraduationCap,
    Coins,
    LogOut,
    Menu,
    X,
    User as UserIcon,
    Shield,
} from 'lucide-react';

interface NavbarProps {
    onToggleMobileSidebar?: () => void;
    showSidebarToggle?: boolean;
}

export const Navbar: React.FC<NavbarProps> = ({
    onToggleMobileSidebar,
    showSidebarToggle = false,
}) => {
    const { user, isAuthenticated, logout } = useAuth();
    const navigate = useNavigate();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userDropdownOpen, setUserDropdownOpen] = useState(false);

    const handleLogout = async () => {
        await logout();
        navigate('/connexion', { replace: true });
    };

    const isAdmin = user?.role === 'administrateur';

    return (
        <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-xs border-b border-slate-200/80 shadow-2xs">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-16">
                    {/* Partie Gauche : Toggle Sidebar + Logo */}
                    <div className="flex items-center gap-3">
                        {showSidebarToggle && (
                            <button
                                type="button"
                                onClick={onToggleMobileSidebar}
                                aria-label="Ouvrir le menu de navigation latérale"
                                className="lg:hidden p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors"
                            >
                                <Menu className="w-5 h-5" />
                            </button>
                        )}

                        <Link to={isAuthenticated ? (isAdmin ? '/admin' : '/app') : '/'} className="flex items-center gap-2.5 group">
                            <div className="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-xs group-hover:bg-indigo-700 transition-colors">
                                <GraduationCap className="w-5 h-5" />
                            </div>
                            <div className="flex flex-col">
                                <span className="text-base font-bold text-slate-900 tracking-tight leading-none">
                                    SkillSwap
                                </span>
                                <span className="text-[10px] font-medium text-indigo-600 tracking-wider uppercase mt-0.5">
                                    Campus
                                </span>
                            </div>
                        </Link>
                    </div>

                    {/* Navigation Desktop Centrée selon le Rôle */}
                    <nav className="hidden md:flex items-center gap-1 text-sm font-medium text-slate-600">
                        {!isAuthenticated ? (
                            <>
                                <NavLink
                                    to="/"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Accueil
                                </NavLink>
                                <NavLink
                                    to="/catalogue"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Catalogue
                                </NavLink>
                            </>
                        ) : isAdmin ? (
                            <>
                                <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-900 text-slate-100 text-xs font-semibold mr-2">
                                    <Shield className="w-3.5 h-3.5 text-indigo-400" />
                                    <span>Espace Admin</span>
                                </div>
                                <NavLink
                                    to="/admin"
                                    end
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Tableau de bord
                                </NavLink>
                                <NavLink
                                    to="/admin/users"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Utilisateurs
                                </NavLink>
                                <NavLink
                                    to="/admin/categories"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Catégories
                                </NavLink>
                                <NavLink
                                    to="/admin/offres"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Modération
                                </NavLink>
                                <NavLink
                                    to="/admin/litiges"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Litiges
                                </NavLink>
                            </>
                        ) : (
                            <>
                                <NavLink
                                    to="/app"
                                    end
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Accueil
                                </NavLink>
                                <NavLink
                                    to="/app/catalogue"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Catalogue
                                </NavLink>
                                <NavLink
                                    to="/app/mes-offres"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Mes offres
                                </NavLink>
                                <NavLink
                                    to="/app/planning"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Planning
                                </NavLink>
                                <NavLink
                                    to="/app/reservations"
                                    className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg transition-colors ${
                                            isActive ? 'text-indigo-600 font-semibold bg-indigo-50/50' : 'hover:text-slate-900 hover:bg-slate-50'
                                        }`
                                    }
                                >
                                    Réservations
                                </NavLink>
                            </>
                        )}
                    </nav>

                    {/* Partie Droite : Connexion/Inscription OU Profil + Solde */}
                    <div className="flex items-center gap-3">
                        {!isAuthenticated ? (
                            <div className="flex items-center gap-2">
                                <Link
                                    to="/connexion"
                                    className="px-3.5 py-1.5 rounded-xl text-slate-700 hover:text-slate-900 text-xs font-semibold hover:bg-slate-100 transition-colors"
                                >
                                    Connexion
                                </Link>
                                <Link
                                    to="/inscription"
                                    className="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors"
                                >
                                    Inscription (+2 crédits)
                                </Link>
                            </div>
                        ) : (
                            <div className="flex items-center gap-3">
                                {/* Badge Solde Temps Réel (RG16) */}
                                {!isAdmin && user?.portefeuille && (
                                    <Link
                                        to="/app/profil"
                                        title="Voir les détails de mon portefeuille"
                                        className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold hover:bg-amber-100/70 transition-colors"
                                    >
                                        <Coins className="w-3.5 h-3.5 text-amber-600" />
                                        <span>{user.portefeuille.solde_disponible} h</span>
                                        {user.portefeuille.credits_bloques > 0 && (
                                            <span className="text-[10px] text-amber-700 font-normal">
                                                (+{user.portefeuille.credits_bloques} bloq.)
                                            </span>
                                        )}
                                    </Link>
                                )}

                                {/* Menu Profil Déroulant */}
                                <div className="relative">
                                    <button
                                        type="button"
                                        onClick={() => setUserDropdownOpen((o) => !o)}
                                        aria-expanded={userDropdownOpen}
                                        className="flex items-center gap-2 p-1 pl-2 pr-1.5 rounded-xl border border-slate-200 hover:border-slate-300 bg-white transition-colors text-left"
                                    >
                                        <div className="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center uppercase">
                                            {user?.prenom?.[0] || 'U'}{user?.nom?.[0] || ''}
                                        </div>
                                        <div className="hidden sm:block text-xs leading-tight">
                                            <p className="font-semibold text-slate-900 truncate max-w-[120px]">
                                                {user?.prenom} {user?.nom}
                                            </p>
                                            <p className="text-[10px] text-slate-500 capitalize">{user?.role}</p>
                                        </div>
                                    </button>

                                    {userDropdownOpen && (
                                        <div
                                            className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-1.5 z-50 animate-scaleUp"
                                            onMouseLeave={() => setUserDropdownOpen(false)}
                                        >
                                            <div className="px-3 py-2 border-b border-slate-100 text-xs">
                                                <p className="font-semibold text-slate-900">{user?.prenom} {user?.nom}</p>
                                                <p className="text-slate-400 text-[10px] truncate">{user?.email}</p>
                                            </div>

                                            {isAdmin ? (
                                                <Link
                                                    to="/app"
                                                    onClick={() => setUserDropdownOpen(false)}
                                                    className="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50"
                                                >
                                                    <GraduationCap className="w-3.5 h-3.5 text-indigo-600" />
                                                    Basculer vue Étudiant
                                                </Link>
                                            ) : (
                                                <Link
                                                    to="/app/profil"
                                                    onClick={() => setUserDropdownOpen(false)}
                                                    className="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50"
                                                >
                                                    <UserIcon className="w-3.5 h-3.5 text-slate-500" />
                                                    Mon profil & crédits
                                                </Link>
                                            )}

                                            <button
                                                type="button"
                                                onClick={handleLogout}
                                                className="w-full text-left flex items-center gap-2 px-3 py-2 text-xs text-rose-600 hover:bg-rose-50 transition-colors"
                                            >
                                                <LogOut className="w-3.5 h-3.5" />
                                                Se déconnecter
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Hamburger mobile */}
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen((o) => !o)}
                            aria-label="Menu principal"
                            className="md:hidden p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100"
                        >
                            {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                        </button>
                    </div>
                </div>
            </div>

            {/* Menu Mobile Repliable */}
            {mobileMenuOpen && (
                <div className="md:hidden border-t border-slate-100 bg-white px-4 py-3 space-y-1">
                    {!isAuthenticated ? (
                        <>
                            <Link
                                to="/"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Accueil
                            </Link>
                            <Link
                                to="/catalogue"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Catalogue
                            </Link>
                        </>
                    ) : isAdmin ? (
                        <>
                            <Link
                                to="/admin"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Tableau de bord Admin
                            </Link>
                            <Link
                                to="/admin/users"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Gestion des utilisateurs
                            </Link>
                            <Link
                                to="/admin/categories"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Catégories
                            </Link>
                            <Link
                                to="/admin/offres"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Modération des offres
                            </Link>
                            <Link
                                to="/admin/litiges"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Arbitrage des litiges
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link
                                to="/app"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Tableau de bord
                            </Link>
                            <Link
                                to="/app/catalogue"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Catalogue d'offres
                            </Link>
                            <Link
                                to="/app/mes-offres"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Mes offres
                            </Link>
                            <Link
                                to="/app/planning"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Planning des séances
                            </Link>
                            <Link
                                to="/app/reservations"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Réservations
                            </Link>
                            <Link
                                to="/app/profil"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Mon profil & crédits ({user?.portefeuille?.solde_disponible ?? 0} h)
                            </Link>
                        </>
                    )}
                </div>
            )}
        </header>
    );
};

export default Navbar;
