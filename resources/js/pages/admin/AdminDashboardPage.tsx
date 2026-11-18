/**
 * SkillSwap Campus — Back-Office : Tableau de Bord Administrateur (Module A)
 */

import React, { useEffect, useState, useCallback } from 'react';
import {
    Users,
    Layers,
    Calendar,
    Clock,
    ShieldAlert,
    Coins,
    Lock,
    TrendingUp,
    RefreshCw,
    UserCheck,
    UserX,
    Archive,
    CheckCircle2,
} from 'lucide-react';
import { adminApi } from '../../api/admin.api';
import { AdminStats } from '../../types/admin.types';
import Button from '../../components/common/Button';
import AlertBanner from '../../components/common/AlertBanner';
import Card from '../../components/common/Card';
import Spinner from '../../components/common/Spinner';

export const AdminDashboardPage: React.FC = () => {
    const [stats, setStats] = useState<AdminStats | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const chargerStats = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await adminApi.getStats();
            setStats(data.stats);
        } catch {
            setError("Impossible de charger les statistiques de pilotage de la plateforme.");
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        chargerStats();
    }, [chargerStats]);

    if (isLoading && !stats) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[60vh] gap-3">
                <Spinner size="lg" />
                <p className="text-sm font-medium text-slate-500">
                    Chargement des indicateurs de pilotage de la plateforme...
                </p>
            </div>
        );
    }

    if (error && !stats) {
        return (
            <div className="max-w-2xl mx-auto mt-12 space-y-4">
                <AlertBanner
                    variant="danger"
                    title="Erreur de chargement"
                    message={error}
                />
                <div className="flex justify-center">
                    <Button
                        variant="secondary"
                        leftIcon={<RefreshCw className="w-4 h-4" />}
                        onClick={chargerStats}
                    >
                        Réessayer
                    </Button>
                </div>
            </div>
        );
    }

    const {
        utilisateurs = { total: 0, etudiants: 0, administrateurs: 0, actifs: 0, desactives: 0 },
        offres = { total: 0, publiees: 0, brouillons: 0, archivees: 0 },
        reservations = { total: 0, en_attente: 0, acceptees: 0, realisees: 0, annulees: 0, en_litige: 0, refusees: 0 },
        activite_financiere = { seances_realisees: 0, credits_transferes: 0, credits_sequestres: 0, litiges_ouverts: 0, litiges_resolus: 0 },
        evolution_mensuelle = [],
    } = stats || {};

    const maxMonthlyTotal = Math.max(...evolution_mensuelle.map(m => m.total), 1);

    return (
        <div className="space-y-8 pb-12">
            {/* Entête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Indicateurs & Pilotage Campus</span>
                        <span className="text-xs px-2.5 py-0.5 rounded-full font-bold bg-indigo-100 text-indigo-700 uppercase tracking-wide">
                            Administrateur
                        </span>
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Surveillance en temps réel des flux financiers, des utilisateurs, du catalogue et des litiges.
                    </p>
                </div>
                <Button
                    variant="secondary"
                    size="sm"
                    leftIcon={<RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />}
                    onClick={chargerStats}
                    disabled={isLoading}
                >
                    Actualiser
                </Button>
            </div>

            {error && (
                <AlertBanner
                    variant="danger"
                    message={error}
                    onClose={() => setError(null)}
                />
            )}

            {/* Avertissement financier strict RG16 */}
            <div className="bg-indigo-50 border border-indigo-200/80 rounded-2xl p-4 text-xs text-indigo-900 flex items-start gap-3">
                <Coins className="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                <div className="leading-relaxed">
                    <p className="font-bold">Intégrité Financière & Règle RG16</p>
                    <p className="text-indigo-700 mt-0.5">
                        1 crédit = 1 heure de partage. Le système distingue strictement les <strong>crédits séquestrés</strong> (bloqués temporairement pour des séances en attente ou litiges) des <strong>crédits définitivement transférés</strong> suite à double confirmation ou arbitrage.
                    </p>
                </div>
            </div>

            {/* Cartes financières clés */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div className="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-3xl p-5 text-white shadow-md shadow-indigo-600/15 flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-indigo-100">
                            Crédits Transférés
                        </span>
                        <div className="p-2 rounded-xl bg-white/20 backdrop-blur-xs">
                            <Coins className="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div>
                        <div className="text-3xl font-black tracking-tight">
                            {activite_financiere.credits_transferes} <span className="text-base font-bold">crédits</span>
                        </div>
                        <p className="text-[11px] text-indigo-100 mt-1">
                            Heures d'échange définitivement validées
                        </p>
                    </div>
                </div>

                <div className="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Crédits Bloqués (Séquestre)
                        </span>
                        <div className="p-2 rounded-xl bg-amber-50 text-amber-600">
                            <Lock className="w-5 h-5" />
                        </div>
                    </div>
                    <div>
                        <div className="text-3xl font-black text-slate-900 tracking-tight">
                            {activite_financiere.credits_sequestres} <span className="text-base font-bold text-slate-600">crédits</span>
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">
                            En cours de séance ou en litige ouvert
                        </p>
                    </div>
                </div>

                <div className="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Séances Terminées
                        </span>
                        <div className="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                    </div>
                    <div>
                        <div className="text-3xl font-black text-slate-900 tracking-tight">
                            {activite_financiere.seances_realisees}
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">
                            Séances d'échange accomplies avec succès
                        </p>
                    </div>
                </div>

                <div className="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Litiges Ouverts
                        </span>
                        <div className="p-2 rounded-xl bg-rose-50 text-rose-600">
                            <ShieldAlert className="w-5 h-5" />
                        </div>
                    </div>
                    <div>
                        <div className="text-3xl font-black text-rose-600 tracking-tight">
                            {activite_financiere.litiges_ouverts}
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">
                            {activite_financiere.litiges_resolus} litige{activite_financiere.litiges_resolus > 1 ? 's' : ''} résolu{activite_financiere.litiges_resolus > 1 ? 's' : ''} au total
                        </p>
                    </div>
                </div>
            </div>

            {/* Sections Métriques Détaillées */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Utilisateurs */}
                <Card>
                    <div className="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div className="flex items-center gap-2">
                            <Users className="w-4 h-4 text-indigo-600" />
                            <h2 className="text-sm font-bold text-slate-900">Communauté & Comptes</h2>
                        </div>
                        <span className="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                            {utilisateurs.total} au total
                        </span>
                    </div>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="flex items-center gap-2 text-slate-700">
                                <Users className="w-3.5 h-3.5 text-slate-500" /> Étudiants inscrits
                            </span>
                            <span className="font-bold text-slate-900">{utilisateurs.etudiants}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="flex items-center gap-2 text-emerald-700">
                                <UserCheck className="w-3.5 h-3.5 text-emerald-600" /> Comptes actifs
                            </span>
                            <span className="font-bold text-emerald-700">{utilisateurs.actifs}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="flex items-center gap-2 text-rose-700">
                                <UserX className="w-3.5 h-3.5 text-rose-600" /> Comptes désactivés (RG18)
                            </span>
                            <span className="font-bold text-rose-700">{utilisateurs.desactives}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="text-slate-600">Administrateurs plateforme</span>
                            <span className="font-bold text-indigo-600">{utilisateurs.administrateurs}</span>
                        </div>
                    </div>
                </Card>

                {/* Offres */}
                <Card>
                    <div className="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div className="flex items-center gap-2">
                            <Layers className="w-4 h-4 text-indigo-600" />
                            <h2 className="text-sm font-bold text-slate-900">Offres de Compétences</h2>
                        </div>
                        <span className="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                            {offres.total} au total
                        </span>
                    </div>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="flex items-center gap-2 text-emerald-700">
                                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" /> Offres publiées
                            </span>
                            <span className="font-bold text-emerald-700">{offres.publiees}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="flex items-center gap-2 text-amber-700">
                                <Clock className="w-3.5 h-3.5 text-amber-600" /> Brouillons étudiants
                            </span>
                            <span className="font-bold text-amber-700">{offres.brouillons}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="flex items-center gap-2 text-slate-700">
                                <Archive className="w-3.5 h-3.5 text-slate-500" /> Offres archivées (RG17)
                            </span>
                            <span className="font-bold text-slate-900">{offres.archivees}</span>
                        </div>
                    </div>
                </Card>

                {/* Réservations */}
                <Card>
                    <div className="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div className="flex items-center gap-2">
                            <Calendar className="w-4 h-4 text-indigo-600" />
                            <h2 className="text-sm font-bold text-slate-900">Cycle des Réservations</h2>
                        </div>
                        <span className="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                            {reservations.total} demandes
                        </span>
                    </div>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="text-amber-700">En attente d'acceptation</span>
                            <span className="font-bold text-amber-700">{reservations.en_attente}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="text-indigo-700">Acceptées / programmées</span>
                            <span className="font-bold text-indigo-700">{reservations.acceptees}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="text-emerald-700">Séances réalisées</span>
                            <span className="font-bold text-emerald-700">{reservations.realisees}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="text-rose-700">En litige ouvert</span>
                            <span className="font-bold text-rose-700">{reservations.en_litige}</span>
                        </div>
                        <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 text-xs">
                            <span className="text-slate-500">Annulées ou refusées</span>
                            <span className="font-bold text-slate-600">{reservations.annulees + reservations.refusees}</span>
                        </div>
                    </div>
                </Card>
            </div>

            {/* Graphique de l'évolution mensuelle */}
            <Card>
                <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="w-5 h-5 text-indigo-600" />
                        <div>
                            <h2 className="text-base font-bold text-slate-900">
                                Évolution Mensuelle des Demandes
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Volume mensuel des réservations créées sur les 6 derniers mois.
                            </p>
                        </div>
                    </div>
                </div>

                {evolution_mensuelle.length === 0 ? (
                    <div className="p-8 text-center text-xs text-slate-400">
                        Aucune donnée d'évolution disponible pour les 6 derniers mois.
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="grid grid-cols-6 gap-3 items-end h-48 pt-6 pb-2 border-b border-slate-100">
                            {evolution_mensuelle.map((item, idx) => {
                                const heightPercent = Math.max(Math.round((item.total / maxMonthlyTotal) * 100), 8);
                                return (
                                    <div key={idx} className="flex flex-col items-center justify-end h-full gap-2">
                                        <span className="text-xs font-bold text-slate-700">
                                            {item.total}
                                        </span>
                                        <div
                                            className="w-full max-w-[48px] bg-indigo-600 hover:bg-indigo-700 transition-all rounded-t-xl"
                                            style={{ height: `${heightPercent}%` }}
                                            title={`${item.mois} : ${item.total} réservations`}
                                        />
                                        <span className="text-[11px] font-medium text-slate-500 mt-1 truncate max-w-full">
                                            {item.mois}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        <p className="text-[11px] text-slate-400 text-center">
                            Données agrégées depuis la base transactionnelle Laravel.
                        </p>
                    </div>
                )}
            </Card>
        </div>
    );
};

export default AdminDashboardPage;
