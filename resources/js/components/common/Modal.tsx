/**
 * SkillSwap Campus — Composant Modal Accessible & Réutilisable
 */

import React, { useEffect, useRef } from 'react';
import { X } from 'lucide-react';

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl';

export interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    children: React.ReactNode;
    footer?: React.ReactNode;
    size?: ModalSize;
    closeOnEscape?: boolean;
    closeOnBackdropClick?: boolean;
}

const sizeClasses: Record<ModalSize, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-2xl',
};

export const Modal: React.FC<ModalProps> = ({
    isOpen,
    onClose,
    title,
    description,
    children,
    footer,
    size = 'md',
    closeOnEscape = true,
    closeOnBackdropClick = true,
}) => {
    const modalRef = useRef<HTMLDivElement>(null);

    // Fermeture avec la touche Échap
    useEffect(() => {
        if (!isOpen || !closeOnEscape) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, closeOnEscape, onClose]);

    // Blocage du scroll de la page lorsque la modale est ouverte
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            {/* Backdrop avec flou discret */}
            <div
                className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity duration-200 animate-fadeIn"
                onClick={closeOnBackdropClick ? onClose : undefined}
                aria-hidden="true"
            />

            {/* Fenêtre Modale */}
            <div
                ref={modalRef}
                className={`
                    relative w-full ${sizeClasses[size]} bg-white rounded-2xl shadow-2xl
                    border border-slate-200/80 overflow-hidden z-10 my-8
                    transform transition-all duration-200 animate-scaleUp
                `}
            >
                {/* En-tête de la modale */}
                <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        {title && (
                            <h3 className="text-base font-semibold text-slate-900 leading-tight">
                                {title}
                            </h3>
                        )}
                        {description && (
                            <p className="text-xs text-slate-500 mt-0.5">
                                {description}
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fermer la boîte de dialogue"
                        className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Contenu */}
                <div className="p-6 text-sm text-slate-700 max-h-[70vh] overflow-y-auto">
                    {children}
                </div>

                {/* Pied de page optionnel */}
                {footer && (
                    <div className="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
};

export default Modal;
