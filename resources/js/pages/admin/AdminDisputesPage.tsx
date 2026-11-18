/**
 * SkillSwap Campus — Back-Office : Arbitrage des Litiges (Module E - RG12, RG13, RG14)
 */

import React, { useEffect, useState, useCallback } from 'react';
import {
    ShieldAlert,
    RefreshCw,
    Coins,
    FileText,
    Gavel,
    ShieldCheck,
} from 'lucide-react';
import { adminApi } from '../../api/admin.api';
import { Litige, LitigeDecision } from '../../types/admin.types';
import Button from '../../components/common/Button';
import Badge from '../../components/common/Badge';
import AlertBanner from '../../components/common/AlertBanner';
import Modal from '../../components/common/Modal';
import Spinner from '../../components/common/Spinner';

export const AdminDisputesPage: React.FC = () => {
    const [litiges, setLitiges] = useState<Litige[]>([]);
    const [total, setTotal] = useState(0);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    // Onglet 'ouvert' (par défaut) ou 'resolu'
    const [activeTab, setActiveTab] = useState<'ouvert' | 'resolu'>('ouvert');

    // Modale Détail Dossier Litige
    const [selectedLitige, setSelectedLitige] = useState<Litige | null>(null);

    // Modale Arbitrage Administratif (RG13, RG14)
    const [litigeToArbitrate, setLitigeToArbitrate] = useState<Litige | null>(null);
    const [decisionChoice, setDecisionChoice] = useState<LitigeDecision>('restitution_beneficiaire');
    const [arbitrageMotif, setArbitrageMotif] = useState('');
    const [arbitrageError, setArbitrageError] = useState<string | null>(null);
    const [isArbitrating, setIsArbitrating] = useState(false);

    const chargerLitiges = useCallback(async (page = 1) => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await adminApi.getLitiges({
                page,
                statut: activeTab,
            });
            setLitiges(data.litiges || []);
            setTotal(data.total);
            setCurrentPage(data.current_page);
            setLastPage(data.last_page);
        } catch {
            setError("Impossible de charger les dossiers de litige.");
        } finally {
            setIsLoading(false);
        }
    }, [activeTab]);

    useEffect(() => {
        chargerLitiges(1);
    }, [chargerLitiges]);

    // Voir les détails exhaustifs
    const handleViewDetail = async (id: number) => {
        try {
            const data = await adminApi.getLitige(id);
            setSelectedLitige(data.litige);
        } catch {
            setError("Erreur lors de la récupération du dossier de litige.");
        }
    };

    // Ouvrir modale d'arbitrage
    const handleOpenArbitrage = (litige: Litige) => {
        setLitigeToArbitrate(litige);
        setDecisionChoice('restitution_beneficiaire');
        setArbitrageMotif('');
        setArbitrageError(null);
    };

    // Soumettre la décision d'arbitrage (RG13, RG14)
    const handleConfirmArbitrage = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!litigeToArbitrate) return;

        const trimmedMotif = arbitrageMotif.trim();
        if (trimmedMotif.length < 5) {
            setArbitrageError("Le motif officiel de l'arbitrage doit comporter au moins 5 caractères (RG13).");
            return;
        }

        setIsArbitrating(true);
        setArbitrageError(null);
        setError(null);

        try {
            const res = await adminApi.arbitrerLitige(litigeToArbitrate.id, {
                decision: decisionChoice,
                motif: trimmedMotif,
            });
            setSuccessMessage(res.message);
            setLitigeToArbitrate(null);
            if (selectedLitige?.id === litigeToArbitrate.id) {
                setSelectedLitige(null);
            }
            chargerLitiges(currentPage);
        } catch (err: unknown) {
            const msg = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
                : "Erreur lors de l'enregistrement de l'arbitrage.";
            setArbitrageError(msg || "Erreur lors de l'enregistrement de l'arbitrage.");
        } finally {
            setIsArbitrating(false);
        }
    };

    return (
        <div className="space-y-8 pb-12">
            {/* Entête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <Gavel className="w-7 h-7 text-indigo-600" />
                        <span>Arbitrage Administratif des Litiges</span>
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Chambre d'arbitrage des désaccords post-séance et régularisation financière définitive (RG12, RG13, RG14).
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        leftIcon={<RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />}
                        onClick={() => chargerLitiges(currentPage)}
                        disabled={isLoading}
                    >
                        Actualiser
                    </Button>
                </div>
            </div>

            {/* Notifications */}
            {successMessage && (
                <AlertBanner
                    variant="success"
                    message={successMessage}
                    onClose={() => setSuccessMessage(null)}
                />
            )}
            {error && (
                <AlertBanner
                    variant="danger"
                    message={error}
                    onClose={() => setError(null)}
                />
            )}

            {/* Avertissement RG13 & RG14 */}
            <div className="bg-amber-50 border border-amber-200/90 rounded-2xl p-4 text-xs text-amber-950 flex items-start gap-3">
                <ShieldAlert className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div className="leading-relaxed">
                    <p className="font-bold text-amber-900">Règles d'arbitrage impératives (RG13 & RG14)</p>
                    <p className="text-amber-800 mt-0.5">
                        Tant qu'un litige est ouvert, les crédits de la séance restent intégralement séquestrés.
                        <strong> L'arbitrage administratif est définitif et sans appel.</strong> Aucun règlement partiel n'est permis : soit la totalité des crédits est restituée au bénéficiaire, soit elle est transférée à l'intervenant. Tout arbitrage requiert une motivation explicite.
                    </p>
                </div>
            </div>

            {/* Onglets Ouvert vs Résolu */}
            <div className="flex border-b border-slate-200 gap-6 text-xs font-bold">
                <button
                    type="button"
                    onClick={() => setActiveTab('ouvert')}
                    className={`pb-3 border-b-2 transition-colors flex items-center gap-2 ${
                        activeTab === 'ouvert'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    <ShieldAlert className="w-4 h-4" />
                    <span>Dossiers Ouverts (En attente d'arbitrage)</span>
                </button>
                <button
                    type="button"
                    onClick={() => setActiveTab('resolu')}
                    className={`pb-3 border-b-2 transition-colors flex items-center gap-2 ${
                        activeTab === 'resolu'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    <ShieldCheck className="w-4 h-4" />
                    <span>Historique des Litiges Résolus</span>
                </button>
            </div>

            {/* Tableau des Litiges */}
            <div className="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50/80 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                <th className="py-3 px-4">Dossier</th>
                                <th className="py-3 px-4">Déclarant & Date</th>
                                <th className="py-3 px-4">Participants Séance</th>
                                <th className="py-3 px-4 text-center">Enjeu Séquestré</th>
                                <th className="py-3 px-4">Statut / Décision</th>
                                <th className="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {isLoading ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-slate-500">
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <Spinner size="md" />
                                            <span>Chargement des dossiers de litige...</span>
                                        </div>
                                    </td>
                                </tr>
                            ) : litiges.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-slate-400">
                                        {activeTab === 'ouvert'
                                            ? "Aucun dossier de litige en attente d'arbitrage. Tout est régularisé !"
                                            : "Aucun litige résolu dans l'historique."}
                                    </td>
                                </tr>
                            ) : (
                                litiges.map((l) => {
                                    const declarantName = l.declarant
                                        ? `${l.declarant.prenom} ${l.declarant.nom}`
                                        : `Utilisateur #${l.declarant_id}`;
                                    const res = l.reservation;
                                    const coutCredits = res?.cout_credits ?? 1;
                                    const intervenant = res?.creneau?.offre?.intervenant;
                                    const beneficiaire = res?.beneficiaire;

                                    return (
                                        <tr key={l.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-extrabold text-slate-900 text-sm">
                                                    Litige #{l.id}
                                                </div>
                                                <div className="text-slate-500 text-[11px] line-clamp-1 max-w-xs">
                                                    {l.description}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-slate-800">{declarantName}</div>
                                                <div className="text-[11px] text-slate-400">
                                                    {l.created_at ? new Date(l.created_at).toLocaleDateString('fr-FR') : 'Date inconnue'}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="text-[11px] space-y-0.5">
                                                    <div>
                                                        <span className="text-slate-400">Intervenant : </span>
                                                        <span className="font-semibold text-slate-800">
                                                            {intervenant ? `${intervenant.prenom} ${intervenant.nom}` : 'N/A'}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-slate-400">Bénéficiaire : </span>
                                                        <span className="font-semibold text-slate-800">
                                                            {beneficiaire ? `${beneficiaire.prenom} ${beneficiaire.nom}` : 'N/A'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-bold text-xs bg-amber-50 text-amber-700 border border-amber-200">
                                                    <Coins className="w-3.5 h-3.5" />
                                                    {coutCredits} crédit{coutCredits > 1 ? 's' : ''}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
                                                {l.statut === 'ouvert' ? (
                                                    <Badge variant="warning">Arbitrage Requis</Badge>
                                                ) : (
                                                    <div className="space-y-1">
                                                        <Badge variant="success">Résolu</Badge>
                                                        <div className="text-[10px] font-bold text-indigo-700">
                                                            {l.decision === 'transfert_intervenant'
                                                                ? 'Transféré à l\'intervenant'
                                                                : 'Restitué au bénéficiaire'}
                                                        </div>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Voir dossier #${l.id}`}
                                                        leftIcon={<FileText className="w-3.5 h-3.5" />}
                                                        onClick={() => handleViewDetail(l.id)}
                                                    >
                                                        Dossier
                                                    </Button>

                                                    {l.statut === 'ouvert' && (
                                                        <Button
                                                            variant="primary"
                                                            size="sm"
                                                            leftIcon={<Gavel className="w-3.5 h-3.5" />}
                                                            onClick={() => handleOpenArbitrage(l)}
                                                        >
                                                            Arbitrer
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {lastPage > 1 && (
                    <div className="flex items-center justify-between p-4 border-t border-slate-100 bg-slate-50/50 text-xs">
                        <span className="text-slate-500">
                            Page <strong>{currentPage}</strong> sur <strong>{lastPage}</strong> ({total} litiges)
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={currentPage <= 1 || isLoading}
                                onClick={() => chargerLitiges(currentPage - 1)}
                            >
                                Précédent
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={currentPage >= lastPage || isLoading}
                                onClick={() => chargerLitiges(currentPage + 1)}
                            >
                                Suivant
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* Modale Consultation Complète du Dossier */}
            {selectedLitige && (
                <Modal
                    isOpen={true}
                    onClose={() => setSelectedLitige(null)}
                    title={`Dossier d'arbitrage #${selectedLitige.id}`}
                >
                    <div className="space-y-4 text-xs">
                        <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="font-bold text-slate-500 uppercase tracking-wider text-[11px]">
                                    Déclaration officielle du litige
                                </span>
                                <Badge variant={selectedLitige.statut === 'ouvert' ? 'warning' : 'success'}>
                                    {selectedLitige.statut === 'ouvert' ? 'En arbitrage' : 'Résolu'}
                                </Badge>
                            </div>
                            <p className="text-slate-800 text-sm leading-relaxed whitespace-pre-wrap font-medium">
                                "{selectedLitige.description}"
                            </p>
                            <p className="text-[11px] text-slate-500">
                                Déposé par : <strong>{selectedLitige.declarant?.prenom} {selectedLitige.declarant?.nom}</strong> ({selectedLitige.declarant?.email})
                            </p>
                        </div>

                        {/* Détails Séance & Participants */}
                        <div className="grid grid-cols-2 gap-3 p-4 rounded-2xl bg-indigo-50 border border-indigo-100">
                            <div>
                                <span className="text-[11px] text-indigo-700 font-bold">Intervenant</span>
                                <p className="font-bold text-slate-900 mt-0.5">
                                    {selectedLitige.reservation?.creneau?.offre?.intervenant?.prenom} {selectedLitige.reservation?.creneau?.offre?.intervenant?.nom}
                                </p>
                                <p className="text-[11px] text-slate-500">
                                    {selectedLitige.reservation?.creneau?.offre?.intervenant?.email}
                                </p>
                            </div>
                            <div>
                                <span className="text-[11px] text-indigo-700 font-bold">Bénéficiaire</span>
                                <p className="font-bold text-slate-900 mt-0.5">
                                    {selectedLitige.reservation?.beneficiaire?.prenom} {selectedLitige.reservation?.beneficiaire?.nom}
                                </p>
                                <p className="text-[11px] text-slate-500">
                                    {selectedLitige.reservation?.beneficiaire?.email}
                                </p>
                            </div>
                            <div className="col-span-2 pt-2 border-t border-indigo-200/50 flex items-center justify-between">
                                <span className="text-slate-600 font-medium">Montant en séquestre réservé</span>
                                <span className="font-black text-indigo-900 text-sm">
                                    {selectedLitige.reservation?.cout_credits ?? 1} crédit(s)
                                </span>
                            </div>
                        </div>

                        {/* Décision si déjà résolu */}
                        {selectedLitige.statut === 'resolu' && (
                            <div className="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-2">
                                <div className="flex items-center justify-between font-bold text-emerald-950">
                                    <span>Décision Arbitrale Définitive (RG14)</span>
                                    <span className="text-[11px] text-emerald-700">
                                        {selectedLitige.date_resolution ? new Date(selectedLitige.date_resolution).toLocaleDateString('fr-FR') : ''}
                                    </span>
                                </div>
                                <p className="font-black text-emerald-900 text-sm">
                                    {selectedLitige.decision === 'transfert_intervenant'
                                        ? 'Transfert intégral des crédits à l\'intervenant'
                                        : 'Restitution intégrale des crédits au bénéficiaire'}
                                </p>
                                <div className="text-slate-700 text-xs bg-white/70 p-3 rounded-xl border border-emerald-100">
                                    <span className="font-bold">Motif officiel :</span> {selectedLitige.motif_decision}
                                </div>
                                {selectedLitige.administrateur && (
                                    <p className="text-[11px] text-emerald-800">
                                        Arbitré par : {selectedLitige.administrateur.prenom} {selectedLitige.administrateur.nom}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button variant="secondary" onClick={() => setSelectedLitige(null)}>
                                Fermer
                            </Button>
                            {selectedLitige.statut === 'ouvert' && (
                                <Button
                                    variant="primary"
                                    leftIcon={<Gavel className="w-3.5 h-3.5" />}
                                    onClick={() => handleOpenArbitrage(selectedLitige)}
                                >
                                    Rendre la décision arbitrale
                                </Button>
                            )}
                        </div>
                    </div>
                </Modal>
            )}

            {/* Modale d'Arbitrage Officiel (RG13, RG14) */}
            {litigeToArbitrate && (
                <Modal
                    isOpen={true}
                    onClose={() => !isArbitrating && setLitigeToArbitrate(null)}
                    title={`Rendre la décision arbitrale — Dossier #${litigeToArbitrate.id}`}
                >
                    <form onSubmit={handleConfirmArbitrage} className="space-y-4 text-xs">
                        <AlertBanner
                            variant="danger"
                            title="Arbitrage Définitif et Irréversible (RG14)"
                            message="Votre sentence arbitrale est immédiatement exécutée en base de données SQL. Les crédits seront instantanément régularisés et aucun second arbitrage ne sera possible."
                        />

                        {arbitrageError && (
                            <AlertBanner variant="danger" message={arbitrageError} />
                        )}

                        {/* Choix de la décision */}
                        <div className="space-y-2">
                            <label className="block text-slate-800 font-bold">
                                Décision arbitrale exécutoire <span className="text-rose-500">*</span>
                            </label>

                            <div className="space-y-2">
                                <label className={`flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition ${
                                    decisionChoice === 'restitution_beneficiaire'
                                        ? 'bg-indigo-50 border-indigo-400'
                                        : 'bg-white border-slate-200 hover:bg-slate-50'
                                }`}>
                                    <input
                                        type="radio"
                                        name="decision"
                                        value="restitution_beneficiaire"
                                        checked={decisionChoice === 'restitution_beneficiaire'}
                                        onChange={() => setDecisionChoice('restitution_beneficiaire')}
                                        className="mt-1 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <div>
                                        <p className="font-bold text-slate-900">
                                            Restitution des crédits au bénéficiaire (Demandeur)
                                        </p>
                                        <p className="text-[11px] text-slate-500 mt-0.5">
                                            La séance est annulée et les crédits séquestrés retournent immédiatement sur le solde disponible du bénéficiaire.
                                        </p>
                                    </div>
                                </label>

                                <label className={`flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition ${
                                    decisionChoice === 'transfert_intervenant'
                                        ? 'bg-indigo-50 border-indigo-400'
                                        : 'bg-white border-slate-200 hover:bg-slate-50'
                                }`}>
                                    <input
                                        type="radio"
                                        name="decision"
                                        value="transfert_intervenant"
                                        checked={decisionChoice === 'transfert_intervenant'}
                                        onChange={() => setDecisionChoice('transfert_intervenant')}
                                        className="mt-1 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <div>
                                        <p className="font-bold text-slate-900">
                                            Transfert des crédits à l'intervenant (Prestataire)
                                        </p>
                                        <p className="text-[11px] text-slate-500 mt-0.5">
                                            La séance est validée comme réalisée et les crédits sont transférés sur le solde disponible de l'intervenant.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {/* Motif obligatoire */}
                        <div>
                            <label className="block text-slate-800 font-bold mb-1">
                                Motivation de la décision arbitrale (RG13) <span className="text-rose-500">*</span>
                            </label>
                            <textarea
                                rows={3}
                                value={arbitrageMotif}
                                onChange={(e) => setArbitrageMotif(e.target.value)}
                                placeholder="Précisez les constatations, les échanges vérifiés et les raisons motivant votre décision arbitrale..."
                                required
                                minLength={5}
                                maxLength={2000}
                                disabled={isArbitrating}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                            />
                            <p className="text-[11px] text-slate-400 mt-1">
                                Minimum 5 caractères. Ce motif sera notifié aux deux participants et conservé pour audit.
                            </p>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setLitigeToArbitrate(null)}
                                disabled={isArbitrating}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                isLoading={isArbitrating}
                                leftIcon={<Gavel className="w-3.5 h-3.5" />}
                            >
                                Exécuter l'arbitrage définitif
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
};

export default AdminDisputesPage;
