/**
 * SkillSwap Campus — Profil Étudiant, Portefeuille & Évaluations (RG15, RG16)
 */

import { ProfileTools } from './ProfileTools';
import React, { useState, useEffect, useCallback } from 'react';
import {
    Coins,
    Shield,
    Star,
    Lock,
    MessageSquare,
    RefreshCw,
} from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { evaluationsApi } from '../../api/evaluations.api';
import { Evaluation, EvaluationStats } from '../../types/evaluation.types';
import { Button, Badge, Card, Spinner, EmptyState, Pagination } from '../../components/common';

export const ProfilePage: React.FC = () => {
    const { user, refreshUser } = useAuth();

    // État des évaluations reçues
    const [evaluations, setEvaluations] = useState<Evaluation[]>([]);
    const [stats, setStats] = useState<EvaluationStats | null>(null);
    const [currentPage, setCurrentPage] = useState<number>(1);
    const [lastPage, setLastPage] = useState<number>(1);
    const [totalEvals, setTotalEvals] = useState<number>(0);
    const [isLoadingEvals, setIsLoadingEvals] = useState<boolean>(true);
    const [isRefreshing, setIsRefreshing] = useState<boolean>(false);

    // Chargement des avis reçus et des stats
    const chargerEvaluations = useCallback(async (page = 1) => {
        setIsLoadingEvals(true);
        try {
            const data = await evaluationsApi.getRecues(page, 5);
            setEvaluations(data.data);
            setCurrentPage(data.current_page);
            setLastPage(data.last_page);
            setTotalEvals(data.total);

            if (user?.id) {
                const s = await evaluationsApi.getStats(user.id);
                setStats(s);
            }
        } catch {
            // Ignorer si pas encore d'évaluations
        } finally {
            setIsLoadingEvals(false);
        }
    }, [user?.id]);

    useEffect(() => {
        chargerEvaluations(1);
    }, [chargerEvaluations]);

    const handleActualiser = async () => {
        setIsRefreshing(true);
        await refreshUser();
        await chargerEvaluations(currentPage);
        setIsRefreshing(false);
    };

    const soldeDisponible = user?.portefeuille?.solde_disponible ?? user?.solde_disponible ?? 0;
    const creditsBloques = user?.portefeuille?.credits_bloques ?? user?.credits_bloques ?? 0;
    const soldeTotal = user?.portefeuille?.solde_total ?? (soldeDisponible + creditsBloques);

    return (
        <div className="space-y-8">
            {/* En-tête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">
                        Mon Profil & Portefeuille
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Consultez vos données universitaires, vos crédits d'échange et vos avis reçus.
                    </p>
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    onClick={handleActualiser}
                    isLoading={isRefreshing}
                    icon={<RefreshCw className="w-3.5 h-3.5" />}
                >
                    Actualiser les soldes
                </Button>
            </div>

            {/* Cartes Soldes Financiers (RG16) */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {/* Solde Disponible */}
                <div className="bg-gradient-to-br from-amber-500 to-amber-600 rounded-3xl p-5 text-white shadow-md shadow-amber-500/15 flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-amber-100">
                            Solde Disponible
                        </span>
                        <div className="p-2 rounded-xl bg-white/20 backdrop-blur-xs">
                            <Coins className="w-5 h-5 text-white" />
                        </div>
                    </div>

                    <div>
                        <div className="text-3xl sm:text-4xl font-black tracking-tight">
                            {soldeDisponible} <span className="text-lg font-bold">crédit{soldeDisponible > 1 ? 's' : ''}</span>
                        </div>
                        <p className="text-[11px] text-amber-100 mt-1">
                            {soldeDisponible} heure{soldeDisponible > 1 ? 's' : ''} de cours que vous pouvez réserver
                        </p>
                    </div>
                </div>

                {/* Crédits Bloqués */}
                <div className="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Crédits Bloqués (Séquestre)
                        </span>
                        <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                            <Lock className="w-5 h-5" />
                        </div>
                    </div>

                    <div>
                        <div className="text-3xl font-extrabold text-slate-900 tracking-tight">
                            {creditsBloques} <span className="text-sm font-semibold text-slate-500">crédit{creditsBloques > 1 ? 's' : ''}</span>
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">
                            Engagés dans des séances acceptées en attente de réalisation
                        </p>
                    </div>
                </div>

                {/* Solde Total & Règle RG16 */}
                <div className="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Solde Total Cumulé
                        </span>
                        <div className="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                            <Shield className="w-5 h-5" />
                        </div>
                    </div>

                    <div>
                        <div className="text-3xl font-extrabold text-slate-900 tracking-tight">
                            {soldeTotal} <span className="text-sm font-semibold text-slate-500">crédit{soldeTotal > 1 ? 's' : ''}</span>
                        </div>
                        <p className="text-[11px] text-emerald-700 font-medium mt-1">
                            Règle RG16 : Solde garanti strictement non négatif
                        </p>
                    </div>
                </div>
            </div>

            <ProfileTools />

            {/* Fiche Informations Personnelles */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card title="Identité & Cursus" className="lg:col-span-1">
                    <div className="space-y-4 text-xs">
                        <div className="flex items-center gap-3 pb-3 border-b border-slate-100">
                            <div className="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                                {user?.prenom?.charAt(0) || 'E'}
                            </div>
                            <div>
                                <h3 className="font-bold text-slate-900 text-sm">
                                    {user?.prenom} {user?.nom}
                                </h3>
                                <div className="flex items-center gap-1.5 mt-0.5">
                                    <Badge status={user?.statut} />
                                    <span className="text-[11px] text-slate-400 capitalize">{user?.role}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span className="text-slate-400 block font-medium">Adresse email universitaire</span>
                            <span className="font-semibold text-slate-800">{user?.email}</span>
                        </div>

                        <div>
                            <span className="text-slate-400 block font-medium">Filière / Département d'études</span>
                            <span className="font-semibold text-slate-800">
                                {user?.filiere || 'Non renseignée'}
                            </span>
                        </div>

                        <div>
                            <span className="text-slate-400 block font-medium">Compétences proposées</span>
                            <p className="text-slate-700 mt-0.5 leading-relaxed bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                {user?.competences_proposees || 'Aucune compétence listée pour le moment.'}
                            </p>
                        </div>

                        <div>
                            <span className="text-slate-400 block font-medium">Compétences recherchées</span>
                            <p className="text-slate-700 mt-0.5 leading-relaxed bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                {user?.competences_recherchees || 'Aucune compétence recherchée listée.'}
                            </p>
                        </div>
                    </div>
                </Card>

                {/* Avis Reçus (RG15) */}
                <div className="lg:col-span-2 space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Star className="w-5 h-5 text-amber-500 fill-amber-400" />
                            <h2 className="text-base font-bold text-slate-900">
                                Avis & Évaluations Pédagogiques (RG15)
                            </h2>
                        </div>

                        {stats && (
                            <div className="flex items-center gap-2 text-xs font-semibold text-slate-700 bg-slate-100 px-3 py-1.5 rounded-xl">
                                <span>Note moyenne : </span>
                                <strong className="text-amber-600">
                                    {stats.note_moyenne ? `${stats.note_moyenne} / 5` : 'N/A'}
                                </strong>
                                <span className="text-slate-400">({stats.nombre_evaluations} avis)</span>
                            </div>
                        )}
                    </div>

                    {isLoadingEvals && (
                        <div className="py-12 flex justify-center">
                            <Spinner size="md" />
                        </div>
                    )}

                    {!isLoadingEvals && (
                        <>
                            {evaluations.length === 0 ? (
                                <EmptyState
                                    icon={<MessageSquare className="w-8 h-8" />}
                                    title="Aucun avis pour l'instant"
                                    description="Donnez des séances de tutorat à vos camarades pour recevoir vos premières évaluations bilatérales."
                                />
                            ) : (
                                <div className="space-y-3">
                                    {evaluations.map((ev) => (
                                        <div
                                            key={ev.id}
                                            className="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs space-y-2 text-xs"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[10px]">
                                                        {ev.auteur?.prenom?.charAt(0) || 'E'}
                                                    </div>
                                                    <span className="font-bold text-slate-800">
                                                        {ev.auteur?.prenom} {ev.auteur?.nom}
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-1 font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60">
                                                    <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                                                    <span>{ev.note} / 5</span>
                                                </div>
                                            </div>

                                            {ev.commentaire && (
                                                <p className="text-slate-700 leading-relaxed italic bg-slate-50/70 p-2.5 rounded-xl">
                                                    « {ev.commentaire} »
                                                </p>
                                            )}

                                            <div className="text-[10px] text-slate-400 text-right">
                                                {ev.created_at ? new Date(ev.created_at).toLocaleDateString('fr-FR') : ''}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {lastPage > 1 && (
                                <div className="pt-2 flex justify-center">
                                    <Pagination
                                        currentPage={currentPage}
                                        lastPage={lastPage}
                                        total={totalEvals}
                                        onPageChange={(p) => chargerEvaluations(p)}
                                    />
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};
export default ProfilePage;
