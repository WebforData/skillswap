/**
 * SkillSwap Campus — Composant Footer Réutilisable
 */

import React from 'react';
import { GraduationCap } from 'lucide-react';
import { Link } from 'react-router-dom';

export const Footer: React.FC = () => {
    return (
        <footer className="bg-white border-t border-slate-200/80 py-8 px-4 sm:px-6 lg:px-8 mt-auto">
            <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div className="flex items-center gap-2">
                    <div className="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center">
                        <GraduationCap className="w-3.5 h-3.5" />
                    </div>
                    <span className="font-semibold text-slate-800">SkillSwap Campus</span>
                    <span>&mdash; Plateforme universitaire d'échange équitable de compétences.</span>
                </div>

                <div className="flex items-center gap-6">
                    <Link to="/catalogue" className="hover:text-slate-800 transition-colors">
                        Catalogue
                    </Link>
                    <Link to="/connexion" className="hover:text-slate-800 transition-colors">
                        Connexion
                    </Link>
                    <Link to="/inscription" className="hover:text-slate-800 transition-colors">
                        Rejoindre (+2 crédits)
                    </Link>
                </div>

                <div className="flex items-center gap-1 text-[11px] text-slate-400">
                    <span>Projet académique &bull; 1h = 1 crédit (RG01)</span>
                </div>
            </div>
        </footer>
    );
};

export default Footer;
