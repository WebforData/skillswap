/**
 * SkillSwap Campus — Composant Input Réutilisable avec Label et Erreur
 */

import React, { useId } from 'react';
import { AlertCircle } from 'lucide-react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    helperText?: string;
    icon?: React.ReactNode;
    rightElement?: React.ReactNode;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
    (
        {
            label,
            error,
            helperText,
            icon,
            rightElement,
            id: explicitId,
            className = '',
            disabled,
            required,
            ...rest
        },
        ref
    ) => {
        const generatedId = useId();
        const inputId = explicitId || generatedId;
        const errorId = `${inputId}-error`;
        const helperId = `${inputId}-helper`;

        return (
            <div className="w-full">
                {label && (
                    <label
                        htmlFor={inputId}
                        className="block text-xs font-semibold text-slate-700 mb-1.5"
                    >
                        {label}
                        {required && <span className="text-rose-500 ml-1" aria-hidden="true">*</span>}
                    </label>
                )}

                <div className="relative flex items-center">
                    {icon && (
                        <div className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none flex items-center">
                            {icon}
                        </div>
                    )}

                    <input
                        ref={ref}
                        id={inputId}
                        disabled={disabled}
                        required={required}
                        aria-invalid={!!error}
                        aria-describedby={error ? errorId : helperText ? helperId : undefined}
                        className={`
                            w-full py-2.5 rounded-xl border text-sm transition-colors duration-150
                            placeholder:text-slate-400 focus:outline-none focus:ring-2
                            disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed
                            ${icon ? 'pl-10' : 'pl-3.5'}
                            ${rightElement ? 'pr-11' : 'pr-3.5'}
                            ${
                                error
                                    ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20'
                                    : 'border-slate-300 text-slate-800 focus:border-indigo-600 focus:ring-indigo-500/20'
                            }
                            ${className}
                        `}
                        {...rest}
                    />

                    {rightElement && (
                        <div className="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center">
                            {rightElement}
                        </div>
                    )}
                </div>

                {error ? (
                    <p
                        id={errorId}
                        role="alert"
                        className="mt-1.5 text-xs text-rose-600 flex items-center gap-1 animate-fadeIn"
                    >
                        <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                        <span>{error}</span>
                    </p>
                ) : helperText ? (
                    <p id={helperId} className="mt-1.5 text-xs text-slate-500">
                        {helperText}
                    </p>
                ) : null}
            </div>
        );
    }
);

Input.displayName = 'Input';

export default Input;
