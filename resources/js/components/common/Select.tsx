/**
 * SkillSwap Campus — Composant Select Réutilisable
 */

import React, { useId } from 'react';
import { AlertCircle, ChevronDown } from 'lucide-react';

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    helperText?: string;
    options: SelectOption[];
    placeholder?: string;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            label,
            error,
            helperText,
            options,
            placeholder,
            id: explicitId,
            className = '',
            disabled,
            required,
            ...rest
        },
        ref
    ) => {
        const generatedId = useId();
        const selectId = explicitId || generatedId;
        const errorId = `${selectId}-error`;
        const helperId = `${selectId}-helper`;

        return (
            <div className="w-full">
                {label && (
                    <label
                        htmlFor={selectId}
                        className="block text-xs font-semibold text-slate-700 mb-1.5"
                    >
                        {label}
                        {required && <span className="text-rose-500 ml-1" aria-hidden="true">*</span>}
                    </label>
                )}

                <div className="relative">
                    <select
                        ref={ref}
                        id={selectId}
                        disabled={disabled}
                        required={required}
                        aria-invalid={!!error}
                        aria-describedby={error ? errorId : helperText ? helperId : undefined}
                        className={`
                            w-full pl-3.5 pr-10 py-2.5 rounded-xl border text-sm transition-colors duration-150
                            appearance-none bg-white focus:outline-none focus:ring-2
                            disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed
                            ${
                                error
                                    ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20'
                                    : 'border-slate-300 text-slate-800 focus:border-indigo-600 focus:ring-indigo-500/20'
                            }
                            ${className}
                        `}
                        {...rest}
                    >
                        {placeholder && (
                            <option value="" disabled>
                                {placeholder}
                            </option>
                        )}
                        {options.map((opt) => (
                            <option
                                key={String(opt.value)}
                                value={opt.value}
                                disabled={opt.disabled}
                            >
                                {opt.label}
                            </option>
                        ))}
                    </select>

                    <div className="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                        <ChevronDown className="w-4 h-4" />
                    </div>
                </div>

                {error ? (
                    <p
                        id={errorId}
                        role="alert"
                        className="mt-1.5 text-xs text-rose-600 flex items-center gap-1"
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

Select.displayName = 'Select';

export default Select;
