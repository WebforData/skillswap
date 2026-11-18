/**
 * SkillSwap Campus — Planning Personnel & Calendrier Consolidé (RG08, RG19)
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    Calendar as CalendarIcon,
    Clock,
    MapPin,
    Lock,
    ExternalLink,
    User as UserIcon,
    Coins,
    ShieldCheck,
    CheckCircle2,
} from 'lucide-react';
import { planningApi } from '../../api/planning.api';
import { PlanningItem } from '../../types/reservation.types';
import { Badge, Spinner, EmptyState, AlertBanner } from '../../components/common';

export const PlanningPage: React.FC = () => {
    const [planning, setPlanning] = useState<PlanningItem[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Filtres
    const [roleFiltre, setRoleFiltre] = useState<'tous' | 'intervenant' | 'beneficiaire'>('tous');
    const [periodeFiltre, setPeriodeFiltre] = useState<'tous' | 'a_venir' | 'historique'>('a_venir');

    // Chargement du planning
    const chargerPlanning = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        const params: any = {};
        if (roleFiltre !== 'tous') params.role = roleFiltre;
        if (periodeFiltre === 'a_venir') params.filtre = 'a_venir';
        if (periodeFiltre === 'historique') params.filtre = 'historique';

        try {
            const data = await planningApi.getPlanning(params);
            setPlanning(data.planning);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Impossible de charger votre planning.');
        } finally {
            setIsLoading(false);
        }
    }, [roleFiltre, periodeFiltre]);

    useEffect(() => {
        chargerPlanning();
    }, [chargerPlanning]);

    // Formatage de la date en français
    const formaterDate = (dateStr: string) => {
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('fr-FR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        } catch {
            return dateStr;
        }
    };

    const formaterHeure = (hStr: string) => hStr.substring(0, 5);

    return (
        <div className="space-y-6">
            {/* En-tête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">
                        Mon Planning des Séances
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Consultez vos séances à venir et passées, vos coordonnées d'accès et vos partenaires d'échange.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-xs font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl">
                    <ShieldCheck className="w-4 h-4 text-sky-600" />
                    <span>Séances sécurisées & coordonnées privées (RG19)</span>
                </div>
            </div>

            {/* Barre de filtres combinés */}
            <div className="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                {/* Rôle : Tous, Intervenant, Bénéficiaire */}
                <div className="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl">
                    {[
                        { id: 'tous', label: 'Toutes les séances' },
                        { id: 'intervenant', label: "En tant qu'intervenant" },
                        { id: 'beneficiaire', label: 'En tant que bénéficiaire' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setRoleFiltre(tab.id as any)}
                            className={`
                                px-3 py-1.5 rounded-lg text-xs font-semibold transition-all
                                ${
                                    roleFiltre === tab.id
                                        ? 'bg-white text-indigo-700 shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
                                }
                            `}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Période : À venir, Historique, Toutes */}
                <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-500 font-medium">Période :</span>
                    {[
                        { id: 'a_venir', label: 'À venir' },
                        { id: 'historique', label: 'Historique' },
                        { id: 'tous', label: 'Toutes' },
                    ].map((p) => (
                        <button
                            key={p.id}
                            type="button"
                            onClick={() => setPeriodeFiltre(p.id as any)}
                            className={`
                                px-3 py-1 rounded-lg text-xs font-medium border transition-colors
                                ${
                                    periodeFiltre === p.id
                                        ? 'bg-slate-900 border-slate-900 text-white'
                                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                                }
                            `}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Erreur */}
            {error && (
                <AlertBanner variant="error" message={error} onClose={() => setError(null)} />
            )}

            {/* Chargement */}
            {isLoading && (
                <div className="py-20 flex flex-col items-center justify-center">
                    <Spinner size="lg" />
                    <p className="mt-3 text-xs text-slate-500">Chargement de votre planning...</p>
                </div>
            )}

            {/* Liste du planning */}
            {!isLoading && (
                <>
                    {planning.length === 0 ? (
                        <EmptyState
                            icon={<CalendarIcon className="w-10 h-10" />}
                            title="Aucune séance programmée"
                            description={
                                periodeFiltre === 'a_venir'
                                    ? "Vous n'avez pas de séance prévue prochainement. Explorez le catalogue ou proposez un créneau sur vos offres !"
                                    : "Aucune séance ne correspond aux filtres sélectionnés."
                            }
                        />
                    ) : (
                        <div className="space-y-4">
                            {planning.map((item) => {
                                const isTeacher = item.role === 'intervenant';
                                const hasPrivateCoord = Boolean(item.creneau.lieu_ou_lien_prive);

                                return (
                                    <div
                                        key={item.reservation_id}
                                        className="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-5"
                                    >
                                        <div className="space-y-2 flex-1">
                                            {/* En-tête de la séance */}
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge status={item.statut} />
                                                <span
                                                    className={`
                                                        px-2.5 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider
                                                        ${
                                                            isTeacher
                                                                ? 'bg-indigo-50 text-indigo-700 border border-indigo-200/60'
                                                                : 'bg-emerald-50 text-emerald-700 border border-emerald-200/60'
                                                        }
                                                    `}
                                                >
                                                    {isTeacher ? 'En tant qu’intervenant' : 'En tant que bénéficiaire'}
                                                </span>

                                                <div className="flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60 ml-auto sm:ml-0">
                                                    <Coins className="w-3.5 h-3.5" />
                                                    <span>{item.cout_credits} crédit{item.cout_credits > 1 ? 's' : ''}</span>
                                                </div>
                                            </div>

                                            {/* Titre & Compétence */}
                                            <h3 className="text-base font-bold text-slate-900">
                                                {item.offre?.titre || 'Séance SkillSwap'}
                                            </h3>

                                            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                                                <div className="flex items-center gap-1.5 font-semibold text-slate-800 capitalize">
                                                    <CalendarIcon className="w-4 h-4 text-indigo-600" />
                                                    <span>{formaterDate(item.creneau.date)}</span>
                                                </div>

                                                <div className="flex items-center gap-1.5 font-medium text-slate-700">
                                                    <Clock className="w-4 h-4 text-slate-400" />
                                                    <span>
                                                        {formaterHeure(item.creneau.heure_debut)} &mdash; {formaterHeure(item.creneau.heure_fin)}
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-1.5 text-slate-500">
                                                    <UserIcon className="w-4 h-4 text-slate-400" />
                                                    <span>
                                                        {isTeacher ? 'Bénéficiaire : ' : 'Intervenant : '}
                                                        <strong className="text-slate-800">
                                                            {item.autre_participant?.prenom} {item.autre_participant?.nom}
                                                        </strong>
                                                        {item.autre_participant?.filiere && (
                                                            <span className="text-slate-400"> ({item.autre_participant.filiere})</span>
                                                        )}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Coordonnées & Règle RG19 */}
                                            <div className="pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3 text-xs">
                                                <div className="flex items-center gap-1.5 text-slate-500">
                                                    <MapPin className="w-3.5 h-3.5 text-slate-400" />
                                                    <span>Lieu public : {item.creneau.lieu_public}</span>
                                                </div>

                                                {hasPrivateCoord ? (
                                                    <div className="flex items-center gap-1.5 font-semibold text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                                        <span>Détails privés (RG19) : </span>
                                                        {item.creneau.lieu_ou_lien_prive?.startsWith('http') ? (
                                                            <a
                                                                href={item.creneau.lieu_ou_lien_prive}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="underline hover:text-emerald-900 inline-flex items-center gap-1"
                                                            >
                                                                Rejoindre la visio
                                                                <ExternalLink className="w-3 h-3" />
                                                            </a>
                                                        ) : (
                                                            <span>{item.creneau.lieu_ou_lien_prive}</span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center gap-1.5 text-slate-400 italic">
                                                        <Lock className="w-3.5 h-3.5" />
                                                        <span>Coordonnées privées masquées avant acceptation (RG19)</span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </>
            )}
        </div>
    );
};
export default PlanningPage;
