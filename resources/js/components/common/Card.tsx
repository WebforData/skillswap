/**
 * SkillSwap Campus — Composant Card Conteneur Réutilisable
 */

import React from 'react';

export interface CardProps {
    title?: React.ReactNode;
    subtitle?: React.ReactNode;
    action?: React.ReactNode;
    children: React.ReactNode;
    footer?: React.ReactNode;
    className?: string;
    headerClassName?: string;
    bodyClassName?: string;
}

export const Card: React.FC<CardProps> = ({
    title,
    subtitle,
    action,
    children,
    footer,
    className = '',
    headerClassName = '',
    bodyClassName = '',
}) => {
    const hasHeader = title || subtitle || action;

    return (
        <div
            className={`
                bg-white rounded-2xl border border-slate-200/80 shadow-xs
                transition-all duration-150 overflow-hidden
                ${className}
            `}
        >
            {hasHeader && (
                <div
                    className={`
                        px-6 py-4 border-b border-slate-100 flex items-start justify-between gap-4
                        ${headerClassName}
                    `}
                >
                    <div>
                        {title && (
                            <h3 className="text-base font-semibold text-slate-900 leading-tight">
                                {title}
                            </h3>
                        )}
                        {subtitle && (
                            <p className="text-xs text-slate-500 mt-0.5">
                                {subtitle}
                            </p>
                        )}
                    </div>
                    {action && <div className="shrink-0">{action}</div>}
                </div>
            )}

            <div className={`p-6 ${bodyClassName}`}>
                {children}
            </div>

            {footer && (
                <div className="px-6 py-3.5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                    {footer}
                </div>
            )}
        </div>
    );
};

export default Card;
