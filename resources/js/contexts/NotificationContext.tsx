/**
 * SkillSwap Campus — Contexte et Système de Notifications Toast
 */

import React, { createContext, useCallback, useContext, useState } from 'react';
import { AlertCircle, CheckCircle2, AlertTriangle, Info, X } from 'lucide-react';

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

export interface ToastItem {
    id: string;
    type: NotificationType;
    message: string;
    title?: string;
    duration?: number;
}

export interface NotificationContextType {
    showNotification: (notification: Omit<ToastItem, 'id'>) => string;
    removeNotification: (id: string) => void;
    success: (message: string, title?: string) => string;
    error: (message: string, title?: string) => string;
    warning: (message: string, title?: string) => string;
    info: (message: string, title?: string) => string;
}

export const NotificationContext = createContext<NotificationContextType | undefined>(undefined);

const typeConfig: Record<NotificationType, {
    border: string;
    bg: string;
    text: string;
    icon: React.ComponentType<{ className?: string }>;
    iconColor: string;
}> = {
    success: {
        border: 'border-emerald-200',
        bg: 'bg-white',
        text: 'text-slate-800',
        icon: CheckCircle2,
        iconColor: 'text-emerald-600',
    },
    error: {
        border: 'border-rose-200',
        bg: 'bg-white',
        text: 'text-slate-800',
        icon: AlertCircle,
        iconColor: 'text-rose-600',
    },
    warning: {
        border: 'border-amber-200',
        bg: 'bg-white',
        text: 'text-slate-800',
        icon: AlertTriangle,
        iconColor: 'text-amber-600',
    },
    info: {
        border: 'border-indigo-200',
        bg: 'bg-white',
        text: 'text-slate-800',
        icon: Info,
        iconColor: 'text-indigo-600',
    },
};

export const NotificationProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const removeNotification = useCallback((id: string) => {
        setToasts((prev) => prev.filter((item) => item.id !== id));
    }, []);

    const showNotification = useCallback(
        ({ type, message, title, duration = 4000 }: Omit<ToastItem, 'id'>) => {
            const id = Math.random().toString(36).substring(2, 9);
            const newToast: ToastItem = { id, type, message, title, duration };

            setToasts((prev) => [...prev, newToast]);

            if (duration > 0) {
                setTimeout(() => {
                    removeNotification(id);
                }, duration);
            }

            return id;
        },
        [removeNotification]
    );

    const success = useCallback((message: string, title?: string) => showNotification({ type: 'success', message, title }), [showNotification]);
    const error = useCallback((message: string, title?: string) => showNotification({ type: 'error', message, title }), [showNotification]);
    const warning = useCallback((message: string, title?: string) => showNotification({ type: 'warning', message, title }), [showNotification]);
    const info = useCallback((message: string, title?: string) => showNotification({ type: 'info', message, title }), [showNotification]);

    return (
        <NotificationContext.Provider
            value={{
                showNotification,
                removeNotification,
                success,
                error,
                warning,
                info,
            }}
        >
            {children}

            {/* Conteneur de Toasts ToastContainer */}
            <div
                aria-live="polite"
                aria-atomic="true"
                className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"
            >
                {toasts.map((toast) => {
                    const cfg = typeConfig[toast.type];
                    const Icon = cfg.icon;

                    return (
                        <div
                            key={toast.id}
                            role="status"
                            className={`
                                pointer-events-auto p-4 rounded-xl border shadow-lg
                                flex items-start gap-3 transition-all duration-200
                                ${cfg.bg} ${cfg.border}
                            `}
                        >
                            <Icon className={`w-5 h-5 shrink-0 mt-0.5 ${cfg.iconColor}`} />
                            <div className="flex-1 text-xs">
                                {toast.title && (
                                    <h5 className="font-semibold text-slate-900 mb-0.5">
                                        {toast.title}
                                    </h5>
                                )}
                                <p className="text-slate-600 leading-relaxed">{toast.message}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => removeNotification(toast.id)}
                                aria-label="Fermer la notification"
                                className="text-slate-400 hover:text-slate-600 p-0.5 rounded transition-colors"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                    );
                })}
            </div>
        </NotificationContext.Provider>
    );
};

export const useNotification = (): NotificationContextType => {
    const context = useContext(NotificationContext);
    if (!context) {
        throw new Error("useNotification doit être utilisé au sein d'un composant enveloppé par NotificationProvider.");
    }
    return context;
};

export default NotificationContext;
