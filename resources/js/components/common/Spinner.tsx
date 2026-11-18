/**
 * SkillSwap Campus — Composant Spinner Indicateur de Chargement
 */

import React from 'react';
import { Loader2 } from 'lucide-react';

export type SpinnerSize = 'sm' | 'md' | 'lg';

export interface SpinnerProps {
    size?: SpinnerSize;
    text?: string;
    className?: string;
}

const sizeMap: Record<SpinnerSize, string> = {
    sm: 'w-4 h-4',
    md: 'w-6 h-6',
    lg: 'w-10 h-10',
};

export const Spinner: React.FC<SpinnerProps> = ({
    size = 'md',
    text,
    className = '',
}) => {
    return (
        <div
            role="status"
            className={`inline-flex flex-col items-center justify-center gap-2 text-indigo-600 ${className}`}
        >
            <Loader2 className={`animate-spin ${sizeMap[size]}`} />
            {text && (
                <span className="text-xs font-medium text-slate-600 animate-pulse">
                    {text}
                </span>
            )}
            <span className="sr-only">Chargement en cours...</span>
        </div>
    );
};

export default Spinner;
