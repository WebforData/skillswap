/**
 * SkillSwap Campus — Composant Button Réutilisable
 */

import React from 'react';
import { Loader2 } from 'lucide-react';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    isLoading?: boolean;
    icon?: React.ReactNode;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    isFullWidth?: boolean;
    children: React.ReactNode;
}

const variantClasses: Record<ButtonVariant, string> = {
    primary:
        'bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800 shadow-sm shadow-indigo-200 focus-visible:ring-indigo-500',
    secondary:
        'bg-slate-100 text-slate-800 hover:bg-slate-200 active:bg-slate-300 focus-visible:ring-slate-400',
    outline:
        'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 active:bg-slate-100 focus-visible:ring-indigo-500',
    danger:
        'bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800 shadow-sm shadow-rose-200 focus-visible:ring-rose-500',
    ghost:
        'bg-transparent text-slate-600 hover:bg-slate-100 active:bg-slate-200 hover:text-slate-900 focus-visible:ring-slate-400',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'px-2.5 py-1.5 text-xs rounded-lg gap-1.5',
    md: 'px-4 py-2 text-sm rounded-xl gap-2',
    lg: 'px-5 py-2.5 text-base rounded-xl gap-2.5',
};

export const Button: React.FC<ButtonProps> = ({
    variant = 'primary',
    size = 'md',
    loading = false,
    isLoading = false,
    disabled = false,
    type = 'button',
    icon,
    leftIcon,
    rightIcon,
    isFullWidth = false,
    children,
    className = '',
    onClick,
    ...rest
}) => {
    const isSpinnerActive = loading || isLoading;
    const isDisabled = disabled || isSpinnerActive;
    const effectiveIcon = icon || leftIcon;

    return (
        <button
            type={type}
            disabled={isDisabled}
            onClick={onClick}
            className={`
                inline-flex items-center justify-center font-medium transition-all duration-150
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none
                active:scale-[0.99] select-none
                ${isFullWidth ? 'w-full' : ''}
                ${variantClasses[variant]}
                ${sizeClasses[size]}
                ${className}
            `}
            {...rest}
        >
            {isSpinnerActive ? (
                <Loader2 className="w-4 h-4 animate-spin shrink-0" />
            ) : effectiveIcon ? (
                <span className="shrink-0">{effectiveIcon}</span>
            ) : null}
            <span>{children}</span>
            {!isSpinnerActive && rightIcon ? (
                <span className="shrink-0">{rightIcon}</span>
            ) : null}
        </button>
    );
};

export default Button;
