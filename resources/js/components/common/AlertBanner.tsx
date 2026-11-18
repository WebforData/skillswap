/**
 * SkillSwap Campus — Composant AlertBanner Réutilisable
 */

import React from 'react';
import { AlertCircle, CheckCircle2, AlertTriangle, Info, X } from 'lucide-react';

export type AlertType = 'success' | 'error' | 'warning' | 'info' | 'danger';

export interface AlertBannerProps {
    type?: AlertType;
    variant?: AlertType;
    title?: string;
    message?: React.ReactNode;
    children?: React.ReactNode;
    onClose?: () => void;
    className?: string;
}

const alertConfig: Record<AlertType, {
    container: string;
    iconColor: string;
    titleColor: string;
    textColor: string;
    icon: React.ComponentType<{ className?: string }>;
}> = {
    success: {
        container: 'bg-emerald-50 border-emerald-200/90 text-emerald-900',
        iconColor: 'text-emerald-600',
        titleColor: 'text-emerald-900',
        textColor: 'text-emerald-800',
        icon: CheckCircle2,
    },
    error: {
        container: 'bg-rose-50 border-rose-200/90 text-rose-900',
        iconColor: 'text-rose-600',
        titleColor: 'text-rose-900',
        textColor: 'text-rose-800',
        icon: AlertCircle,
    },
    warning: {
        container: 'bg-amber-50 border-amber-200/90 text-amber-900',
        iconColor: 'text-amber-600',
        titleColor: 'text-amber-900',
        textColor: 'text-amber-800',
        icon: AlertTriangle,
    },
    info: {
        container: 'bg-indigo-50 border-indigo-200/90 text-indigo-900',
        iconColor: 'text-indigo-600',
        titleColor: 'text-indigo-900',
        textColor: 'text-indigo-800',
        icon: Info,
    },
    danger: {
        container: 'bg-rose-50 border-rose-200/90 text-rose-900',
        iconColor: 'text-rose-600',
        titleColor: 'text-rose-900',
        textColor: 'text-rose-800',
        icon: AlertCircle,
    },
};

export const AlertBanner: React.FC<AlertBannerProps> = ({
    type = 'info',
    variant,
    title,
    message,
    children,
    onClose,
    className = '',
}) => {
    const resolvedType = variant || type;
    const config = alertConfig[resolvedType];
    const Icon = config.icon;
    const content = children || message;

    return (
        <div
            role="alert"
            className={`
                p-4 rounded-xl border flex items-start justify-between gap-3 text-xs leading-relaxed
                transition-all duration-150
                ${config.container}
                ${className}
            `}
        >
            <div className="flex items-start gap-3">
                <Icon className={`w-4 h-4 shrink-0 mt-0.5 ${config.iconColor}`} />
                <div>
                    {title && (
                        <p className={`font-semibold mb-0.5 ${config.titleColor}`}>
                            {title}
                        </p>
                    )}
                    <div className={config.textColor}>{content}</div>
                </div>
            </div>

            {onClose && (
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Fermer l'alerte"
                    className="p-1 -mr-1 -mt-1 rounded-lg hover:bg-black/5 text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <X className="w-4 h-4" />
                </button>
            )}
        </div>
    );
};

export default AlertBanner;
