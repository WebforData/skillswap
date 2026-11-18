/**
 * SkillSwap Campus — Composant EmptyState pour Listes Vides
 */

import React from 'react';
import { Inbox } from 'lucide-react';

export interface EmptyStateProps {
    icon?: React.ReactNode;
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
    icon,
    title,
    description,
    action,
    className = '',
}) => {
    return (
        <div
            className={`
                p-8 text-center flex flex-col items-center justify-center
                rounded-2xl border border-dashed border-slate-200 bg-slate-50/50
                ${className}
            `}
        >
            <div className="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-4 shadow-inner">
                {icon || <Inbox className="w-6 h-6" />}
            </div>

            <h4 className="text-sm font-semibold text-slate-800 mb-1">
                {title}
            </h4>

            {description && (
                <p className="text-xs text-slate-500 max-w-sm mb-5 leading-relaxed">
                    {description}
                </p>
            )}

            {action && <div>{action}</div>}
        </div>
    );
};

export default EmptyState;
