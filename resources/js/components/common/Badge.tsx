/**
 * SkillSwap Campus — Composant Badge de Statut Réutilisable
 */

import React from 'react';

export type BadgeStatus =
    // Statuts Réservations
    | 'en_attente'
    | 'pending'
    | 'acceptee'
    | 'accepted'
    | 'refusee'
    | 'refused'
    | 'annulee'
    | 'cancelled'
    | 'realisee'
    | 'completed'
    | 'en_litige'
    | 'dispute'
    // Statuts Offres
    | 'brouillon'
    | 'draft'
    | 'publiee'
    | 'published'
    | 'archivee'
    | 'archived'
    // Niveaux Offres
    | 'debutant'
    | 'intermediaire'
    | 'avance'
    // Statuts Utilisateurs & Litiges
    | 'actif'
    | 'active'
    | 'desactive'
    | 'ouvert'
    | 'resolu';

export type BadgeVariant = 'emerald' | 'amber' | 'indigo' | 'rose' | 'slate' | 'orange' | 'purple' | 'success' | 'warning' | 'danger' | 'neutral' | 'primary';

export interface BadgeProps {
    status?: BadgeStatus;
    variant?: BadgeVariant;
    label?: string;
    size?: 'sm' | 'md';
    className?: string;
    children?: React.ReactNode;
}

interface StatusConfig {
    label: string;
    variant: BadgeVariant;
}

const statusMap: Record<BadgeStatus, StatusConfig> = {
    // Réservations
    en_attente: { label: 'En attente', variant: 'amber' },
    pending: { label: 'En attente', variant: 'amber' },
    acceptee: { label: 'Acceptée', variant: 'indigo' },
    accepted: { label: 'Acceptée', variant: 'indigo' },
    refusee: { label: 'Refusée', variant: 'rose' },
    refused: { label: 'Refusée', variant: 'rose' },
    annulee: { label: 'Annulée', variant: 'slate' },
    cancelled: { label: 'Annulée', variant: 'slate' },
    realisee: { label: 'Réalisée', variant: 'emerald' },
    completed: { label: 'Réalisée', variant: 'emerald' },
    en_litige: { label: 'En litige', variant: 'orange' },
    dispute: { label: 'En litige', variant: 'orange' },

    // Offres
    brouillon: { label: 'Brouillon', variant: 'slate' },
    draft: { label: 'Brouillon', variant: 'slate' },
    publiee: { label: 'Publiée', variant: 'emerald' },
    published: { label: 'Publiée', variant: 'emerald' },
    archivee: { label: 'Archivée', variant: 'slate' },
    archived: { label: 'Archivée', variant: 'slate' },

    // Niveaux Offres
    debutant: { label: 'Débutant', variant: 'emerald' },
    intermediaire: { label: 'Intermédiaire', variant: 'indigo' },
    avance: { label: 'Avancé', variant: 'purple' },

    // Utilisateurs / Litiges
    actif: { label: 'Actif', variant: 'emerald' },
    active: { label: 'Actif', variant: 'emerald' },
    desactive: { label: 'Désactivé', variant: 'rose' },
    ouvert: { label: 'Litige Ouvert', variant: 'orange' },
    resolu: { label: 'Litige Résolu', variant: 'purple' },
};

const variantStyleMap: Record<BadgeVariant, string> = {
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
    amber: 'bg-amber-50 text-amber-700 border-amber-200/80',
    indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
    rose: 'bg-rose-50 text-rose-700 border-rose-200/80',
    slate: 'bg-slate-100 text-slate-700 border-slate-200',
    orange: 'bg-orange-50 text-orange-700 border-orange-200/80',
    purple: 'bg-purple-50 text-purple-700 border-purple-200/80',
    success: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
    warning: 'bg-amber-50 text-amber-700 border-amber-200/80',
    danger: 'bg-rose-50 text-rose-700 border-rose-200/80',
    neutral: 'bg-slate-100 text-slate-700 border-slate-200',
    primary: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
};

export const Badge: React.FC<BadgeProps> = ({
    status,
    variant,
    label,
    size = 'md',
    className = '',
    children,
}) => {
    const config = status ? statusMap[status] : undefined;
    const finalVariant = variant || config?.variant || 'slate';
    const displayText = children || label || config?.label || status;

    const sizeClass = size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs';

    return (
        <span
            className={`
                inline-flex items-center font-medium rounded-full border
                transition-colors duration-150 select-none
                ${variantStyleMap[finalVariant]}
                ${sizeClass}
                ${className}
            `}
        >
            {displayText}
        </span>
    );
};

export default Badge;
