/**
 * SkillSwap Campus — Composant Pagination Compatible Laravel
 */

import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface PaginationProps {
    currentPage: number;
    lastPage: number;
    total?: number;
    perPage?: number;
    onPageChange: (page: number) => void;
    className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({
    currentPage,
    lastPage,
    total,
    perPage,
    onPageChange,
    className = '',
}) => {
    if (lastPage <= 1) return null;

    const hasPrev = currentPage > 1;
    const hasNext = currentPage < lastPage;

    // Calcul de la plage de pages visibles avec ellipse
    const getPageNumbers = () => {
        const pages: (number | string)[] = [];
        const delta = 1;

        for (let i = 1; i <= lastPage; i++) {
            if (
                i === 1 ||
                i === lastPage ||
                (i >= currentPage - delta && i <= currentPage + delta)
            ) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }

        return pages;
    };

    return (
        <nav
            aria-label="Navigation de pagination"
            className={`flex flex-col sm:flex-row items-center justify-between gap-4 py-4 px-2 ${className}`}
        >
            {total !== undefined && (
                <div className="text-xs text-slate-500">
                    Affichage de{' '}
                    <span className="font-semibold text-slate-700">
                        {perPage ? Math.min((currentPage - 1) * perPage + 1, total) : currentPage}
                    </span>{' '}
                    à{' '}
                    <span className="font-semibold text-slate-700">
                        {perPage ? Math.min(currentPage * perPage, total) : currentPage}
                    </span>{' '}
                    sur <span className="font-semibold text-slate-700">{total}</span> résultats
                </div>
            )}

            <div className="inline-flex items-center gap-1.5">
                {/* Bouton Précédent */}
                <button
                    type="button"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={!hasPrev}
                    aria-label="Page précédente"
                    className="p-2 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <ChevronLeft className="w-4 h-4" />
                </button>

                {/* Numéros de page */}
                {getPageNumbers().map((page, idx) => {
                    if (page === '...') {
                        return (
                            <span
                                key={`ellipsis-${idx}`}
                                className="px-2 text-xs text-slate-400 select-none"
                            >
                                &hellip;
                            </span>
                        );
                    }

                    const pageNum = Number(page);
                    const isActive = pageNum === currentPage;

                    return (
                        <button
                            key={pageNum}
                            type="button"
                            onClick={() => onPageChange(pageNum)}
                            aria-current={isActive ? 'page' : undefined}
                            className={`
                                min-w-[34px] h-[34px] px-2 text-xs font-medium rounded-lg transition-colors
                                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500
                                ${
                                    isActive
                                        ? 'bg-indigo-600 text-white font-semibold shadow-xs'
                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                }
                            `}
                        >
                            {pageNum}
                        </button>
                    );
                })}

                {/* Bouton Suivant */}
                <button
                    type="button"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={!hasNext}
                    aria-label="Page suivante"
                    className="p-2 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <ChevronRight className="w-4 h-4" />
                </button>
            </div>
        </nav>
    );
};

export default Pagination;
