/**
 * SkillSwap Campus — Barre Latérale de Navigation (Sidebar Responsive)
 */

import React from 'react';
import { NavLink } from 'react-router-dom';
import {
    LayoutDashboard,
    BookOpen,
    Layers,
    Calendar,
    Clock,
    Star,
    User as UserIcon,
    Users,
    Tag,
    ShieldAlert,
    X,
    Coins,
} from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';

interface NavItem {
    label: string;
    to: string;
    icon: React.ComponentType<{ className?: string }>;
    end?: boolean;
    badge?: string | number;
}

interface SidebarProps {
    type?: 'student' | 'admin';
    isOpen?: boolean;
    onClose?: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({
    type = 'student',
    isOpen = false,
    onClose,
}) => {
    const { user } = useAuth();

    const studentNavItems: NavItem[] = [
        { label: 'Tableau de bord', to: '/app', icon: LayoutDashboard, end: true },
        { label: 'Catalogue public', to: '/app/catalogue', icon: BookOpen },
        { label: 'Mes compétences & offres', to: '/app/mes-offres', icon: Layers },
        { label: 'Planning des séances', to: '/app/planning', icon: Calendar },
        { label: 'Mes réservations', to: '/app/reservations', icon: Clock },
        { label: 'Évaluations & avis', to: '/app/evaluations', icon: Star },
        { label: 'Notifications', to: '/app/notifications', icon: Clock },
        { label: 'Mes litiges', to: '/app/litiges', icon: ShieldAlert },
        { label: 'Profil & Portefeuille', to: '/app/profil', icon: UserIcon },
    ];

    const adminNavItems: NavItem[] = [
        { label: 'Indicateurs & Stats', to: '/admin', icon: LayoutDashboard, end: true },
        { label: 'Gestion des étudiants', to: '/admin/users', icon: Users },
        { label: 'Séances à traiter', to: '/admin/seances', icon: Calendar },
        { label: 'Catégories', to: '/admin/categories', icon: Tag },
        { label: 'Modération des offres', to: '/admin/offres', icon: Layers },
        { label: 'Arbitrage des litiges', to: '/admin/litiges', icon: ShieldAlert },
    ];

    const navItems = type === 'admin' ? adminNavItems : studentNavItems;

    return (
        <>
            {/* Backdrop Mobile */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-2xs lg:hidden transition-opacity"
                    onClick={onClose}
                    aria-hidden="true"
                />
            )}

            {/* Panneau Latéral */}
            <aside
                aria-label="Menu latéral"
                className={`
                    fixed lg:static inset-y-0 left-0 z-40 w-64 bg-white border-r border-slate-200/80
                    flex flex-col justify-between transition-transform duration-200 ease-in-out
                    ${isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}
                `}
            >
                <div>
                    {/* Header mobile avec bouton fermeture */}
                    <div className="p-4 flex items-center justify-between lg:hidden border-b border-slate-100">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Navigation
                        </span>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Fermer le menu latéral"
                            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    {/* Badge Solde Compact Étudiant en tête de sidebar (RG16) */}
                    {type === 'student' && user?.portefeuille && (
                        <div className="p-4 border-b border-slate-100 bg-slate-50/50">
                            <div className="p-3 rounded-xl bg-white border border-amber-200/80 shadow-2xs flex items-center gap-3">
                                <div className="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                    <Coins className="w-5 h-5" />
                                </div>
                                <div className="text-xs leading-tight">
                                    <p className="text-slate-500 font-medium">Temps disponible</p>
                                    <p className="text-sm font-bold text-slate-900 mt-0.5">
                                        {user.portefeuille.solde_disponible} h{' '}
                                        <span className="text-[10px] font-normal text-slate-400">
                                            ({user.portefeuille.solde_total} h total)
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Liens de Navigation */}
                    <nav className="p-3 space-y-1">
                        {navItems.map((item) => {
                            const Icon = item.icon;

                            return (
                                <NavLink
                                    key={item.to}
                                    to={item.to}
                                    end={item.end}
                                    onClick={onClose}
                                    className={({ isActive }) => `
                                        flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold
                                        transition-colors duration-150 select-none
                                        ${
                                            isActive
                                                ? type === 'admin'
                                                    ? 'bg-slate-900 text-white shadow-xs'
                                                    : 'bg-indigo-50 text-indigo-700 shadow-2xs'
                                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'
                                        }
                                    `}
                                >
                                    <div className="flex items-center gap-3">
                                        <Icon className="w-4 h-4 shrink-0" />
                                        <span>{item.label}</span>
                                    </div>
                                    {item.badge !== undefined && (
                                        <span className="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-700 font-bold">
                                            {item.badge}
                                        </span>
                                    )}
                                </NavLink>
                            );
                        })}
                    </nav>
                </div>

                {/* Footer de Sidebar */}
                <div className="p-4 border-t border-slate-100 text-[11px] text-slate-400">
                    <p className="font-semibold text-slate-600">SkillSwap Campus</p>
                    <p className="mt-0.5">Plateforme d'échange de cours 1h = 1 crédit</p>
                </div>
            </aside>
        </>
    );
};

export default Sidebar;
