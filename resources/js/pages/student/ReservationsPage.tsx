import { Link } from 'react-router-dom';
/**
 * SkillSwap Campus — Gestion Complète des Réservations (RG05 à RG14)
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    Inbox,
    Send,
    CheckCircle2,
    XCircle,
    Clock,
    AlertTriangle,
    Coins,
    Calendar,
    User as UserIcon,
    RefreshCw,
} from 'lucide-react';
import { reservationsApi } from '../../api/reservations.api';
import { useAuth } from '../../hooks/useAuth';
import { useNotification } from '../../contexts/NotificationContext';
import { Reservation } from '../../types/reservation.types';
import { Button, Badge, Modal, Spinner, EmptyState, AlertBanner, Pagination } from '../../components/common';

export const ReservationsPage: React.FC = () => {
    const { refreshUser } = useAuth();
    const { success, error: notifyError } = useNotification();

    // Onglet actif : Reçues (intervenant) ou Envoyées (bénéficiaire)
    const [activeTab, setActiveTab] = useState<'recues' | 'envoyees'>('recues');
    const [reservations, setReservations] = useState<Reservation[]>([]);
    const [total, setTotal] = useState<number>(0);
    const [currentPage, setCurrentPage] = useState<number>(1);
    const [lastPage, setLastPage] = useState<number>(1);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Modale : Refus de réservation (Intervenant RG09)
    const [reservationToRefuse, setReservationToRefuse] = useState<Reservation | null>(null);
    const [refusalMotif, setRefusalMotif] = useState<string>('');
    const [isRefusing, setIsRefusing] = useState<boolean>(false);

    // Modale : Annulation de réservation (RG10)
    const [reservationToCancel, setReservationToCancel] = useState<Reservation | null>(null);
    const [cancellationMotif, setCancellationMotif] = useState<string>('');
    const [isCancelling, setIsCancelling] = useState<boolean>(false);

    // Modale : Déclaration de litige (RG12)
    const [reservationForDispute, setReservationForDispute] = useState<Reservation | null>(null);
    const [disputeDescription, setDisputeDescription] = useState<string>('');
    const [isSubmittingDispute, setIsSubmittingDispute] = useState<boolean>(false);

    // Chargement des réservations
    const chargerReservations = useCallback(async (page = 1) => {
        setIsLoading(true);
        setError(null);

        const role = activeTab === 'recues' ? 'intervenant' : 'beneficiaire';

        try {
            const data = await reservationsApi.getMesReservations({
                role,
                page,
                per_page: 10,
            });
            setReservations(data.reservations);
            setTotal(data.total);
            setCurrentPage(data.current_page);
            setLastPage(data.last_page);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Impossible de charger vos réservations.');
        } finally {
            setIsLoading(false);
        }
    }, [activeTab]);

    useEffect(() => {
        setCurrentPage(1);
        chargerReservations(1);
    }, [activeTab, chargerReservations]);

    // Action : Accepter une demande (Intervenant RG06, RG07)
    const handleAccepter = async (res: Reservation) => {
        if (!confirm(`Accepter cette demande de ${res.beneficiaire?.prenom} ${res.beneficiaire?.nom} ? Cela bloquera ${res.cout_credits} crédit(s) et validera le créneau.`)) {
            return;
        }

        try {
            await reservationsApi.accepter(res.id);
            success(`Demande acceptée ! Les ${res.cout_credits} crédit(s) ont été bloqués sur le compte de l'élève (RG06).`);
            await refreshUser();
            chargerReservations(currentPage);
        } catch (err: any) {
            notifyError(err?.response?.data?.message || "Erreur lors de l'acceptation de la réservation.");
        }
    };

    // Action : Confirmer le refus
    const handleConfirmerRefus = async () => {
        if (!reservationToRefuse) return;
        setIsRefusing(true);
        try {
            await reservationsApi.refuser(reservationToRefuse.id, refusalMotif.trim());
            success('Demande de réservation refusée.');
            setReservationToRefuse(null);
            setRefusalMotif('');
            await refreshUser();
            chargerReservations(currentPage);
        } catch (err: any) {
            notifyError(err?.response?.data?.message || 'Erreur lors du refus.');
        } finally {
            setIsRefusing(false);
        }
    };

    // Action : Retirer une demande en attente (Bénéficiaire)
    const handleRetirer = async (res: Reservation) => {
        if (!confirm('Voulez-vous retirer votre demande en attente ? Aucun crédit ne vous sera facturé.')) {
            return;
        }

        try {
            await reservationsApi.retirer(res.id);
            success('Votre demande de réservation a été retirée.');
            await refreshUser();
            chargerReservations(currentPage);
        } catch (err: any) {
            notifyError(err?.response?.data?.message || 'Impossible de retirer la demande.');
        }
    };

    // Action : Confirmer l'annulation pré-séance (RG10)
    const handleConfirmerAnnulation = async () => {
        if (!reservationToCancel) return;
        setIsCancelling(true);
        try {
            await reservationsApi.annuler(reservationToCancel.id, cancellationMotif.trim());
            success('Séance annulée avant le début. Les crédits bloqués ont été intégralement restitués (RG10).');
            setReservationToCancel(null);
            setCancellationMotif('');
            await refreshUser();
            chargerReservations(currentPage);
        } catch (err: any) {
            notifyError(err?.response?.data?.message || "Erreur lors de l'annulation.");
        } finally {
            setIsCancelling(false);
        }
    };

    // Action : Confirmer la séance post-fin (RG11)
    const handleConfirmerSeance = async (res: Reservation) => {
        try {
            const resp = await reservationsApi.confirmer(res.id);
            if (resp.cloturee) {
                success('Séance validée par les deux participants ! Transfert des crédits effectué avec succès (RG11).');
            } else {
                success('Votre confirmation a été enregistrée. En attente de la confirmation de l’autre participant (RG11).');
            }
            await refreshUser();
            chargerReservations(currentPage);
        } catch (err: any) {
            notifyError(err?.response?.data?.message || 'Impossible de confirmer cette séance.');
        }
    };

    // Action : Soumettre un litige (RG12)
    const handleSoumettreLitige = async () => {
        if (!reservationForDispute) return;
        if (disputeDescription.trim().length < 10) {
            notifyError('La description du litige doit comporter au moins 10 caractères.');
            return;
        }

        setIsSubmittingDispute(true);
        try {
            await reservationsApi.declarerLitige(reservationForDispute.id, disputeDescription.trim());
            success('Litige ouvert avec succès. Un administrateur examinera votre dossier (RG12, RG13).');
            setReservationForDispute(null);
            setDisputeDescription('');
            await refreshUser();
            chargerReservations(currentPage);
        } catch (err: any) {
            notifyError(err?.response?.data?.message || "Erreur lors de l'ouverture du litige.");
        } finally {
            setIsSubmittingDispute(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* En-tête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">
                        Mes Réservations & Séances
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Suivez les demandes de cours, validez les séances et gérez les étapes financières de vos échanges.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => chargerReservations(currentPage)}
                        icon={<RefreshCw className="w-3.5 h-3.5" />}
                    >
                        Actualiser
                    </Button>
                </div>
            </div>

            {/* Onglets Reçues / Envoyées */}
            <div className="flex items-center gap-2 border-b border-slate-200">
                <button
                    type="button"
                    onClick={() => setActiveTab('recues')}
                    className={`
                        inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors
                        ${
                            activeTab === 'recues'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }
                    `}
                >
                    <Inbox className="w-4 h-4" />
                    <span>Demandes reçues (Intervenant)</span>
                </button>

                <button
                    type="button"
                    onClick={() => setActiveTab('envoyees')}
                    className={`
                        inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors
                        ${
                            activeTab === 'envoyees'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }
                    `}
                >
                    <Send className="w-4 h-4" />
                    <span>Demandes envoyées (Bénéficiaire)</span>
                </button>
            </div>

            {/* Message d'erreur */}
            {error && (
                <AlertBanner variant="error" message={error} onClose={() => setError(null)} />
            )}

            {/* Chargement */}
            {isLoading && (
                <div className="py-20 flex flex-col items-center justify-center">
                    <Spinner size="lg" />
                    <p className="mt-3 text-xs text-slate-500">Chargement des réservations...</p>
                </div>
            )}

            {/* Liste des réservations */}
            {!isLoading && (
                <>
                    {reservations.length === 0 ? (
                        <EmptyState
                            icon={activeTab === 'recues' ? <Inbox className="w-10 h-10" /> : <Send className="w-10 h-10" />}
                            title={activeTab === 'recues' ? 'Aucune demande reçue' : 'Aucune demande envoyée'}
                            description={
                                activeTab === 'recues'
                                    ? "Vous n'avez pas encore reçu de demande sur vos créneaux. Proposez de nouveaux créneaux pour attirer d'autres étudiants !"
                                    : "Vous n'avez envoyé aucune demande de réservation pour le moment. Explorez le catalogue pour apprendre !"
                            }
                        />
                    ) : (
                        <div className="space-y-4">
                            {reservations.map((res) => {
                                const isIntervenant = activeTab === 'recues';
                                const isPending = res.statut === 'en_attente';
                                const start = new Date(`${res.creneau?.date}T${res.creneau?.heure_debut}`).getTime();
                                const end = new Date(`${res.creneau?.date}T${res.creneau?.heure_fin}`).getTime();
                                const isAccepted = res.statut === 'acceptee';
                                const isCompleted = res.statut === 'realisee';
                                const isDispute = res.statut === 'en_litige';

                                const hasConfirmed = isIntervenant
                                    ? res.confirmation_intervenant
                                    : res.confirmation_beneficiaire;

                                const otherHasConfirmed = isIntervenant
                                    ? res.confirmation_beneficiaire
                                    : res.confirmation_intervenant;

                                return (
                                    <div
                                        key={res.id}
                                        data-testid={`reservation-${res.id}`}
                                        className="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs hover:border-slate-300 transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-5"
                                    >
                                        <div className="space-y-2 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge status={res.statut} />
                                                <div className="flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60">
                                                    <Coins className="w-3.5 h-3.5" />
                                                    <span>{res.cout_credits} crédit{res.cout_credits > 1 ? 's' : ''}</span>
                                                </div>
                                            </div>

                                            <h3 className="text-base font-bold text-slate-900">
                                                {res.offre?.titre || 'Cours particulier SkillSwap'}
                                            </h3>

                                            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                                                <div className="flex items-center gap-1 font-semibold text-slate-800">
                                                    <Calendar className="w-3.5 h-3.5 text-indigo-600" />
                                                    <span>{res.creneau?.date}</span>
                                                </div>

                                                <div className="flex items-center gap-1 font-medium text-slate-700">
                                                    <Clock className="w-3.5 h-3.5 text-slate-400" />
                                                    <span>
                                                        {res.creneau?.heure_debut.substring(0, 5)} &mdash; {res.creneau?.heure_fin.substring(0, 5)}
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-1.5 text-slate-600">
                                                    <UserIcon className="w-3.5 h-3.5 text-slate-400" />
                                                    <span>
                                                        {isIntervenant ? (
                                                            <>Élève demandeur : <strong>{res.beneficiaire?.prenom} {res.beneficiaire?.nom}</strong></>
                                                        ) : (
                                                            <>Intervenant : <strong>{res.offre?.intervenant?.prenom} {res.offre?.intervenant?.nom}</strong></>
                                                        )}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Note sur les confirmations bilatérales RG11 */}
                                            {isAccepted && (
                                                <div className="text-[11px] p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-2">
                                                    <span className="text-slate-600">
                                                        État de confirmation post-séance (RG11) :
                                                    </span>
                                                    <div className="flex items-center gap-3 font-medium">
                                                        <span className={hasConfirmed ? 'text-emerald-700 font-semibold' : 'text-slate-400'}>
                                                            Vous : {hasConfirmed ? 'Confirmé ✓' : 'Non confirmé'}
                                                        </span>
                                                        <span className={otherHasConfirmed ? 'text-emerald-700 font-semibold' : 'text-slate-400'}>
                                                            Partenaire : {otherHasConfirmed ? 'Confirmé ✓' : 'En attente'}
                                                        </span>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Motif de refus si refusée */}
                                            {res.motif_refus && (
                                                <div className="text-xs text-rose-700 bg-rose-50 p-2.5 rounded-xl border border-rose-200">
                                                    <strong>Motif du refus : </strong>{res.motif_refus}
                                                </div>
                                            )}
                                        </div>

                                        {/* Actions autorisées */}
                                        <div className="flex flex-wrap items-center gap-2 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                                            {/* Demande en attente */}
                                            {isPending && isIntervenant && (
                                                <>
                                                    <Button
                                                        variant="primary"
                                                        size="sm"
                                                        onClick={() => handleAccepter(res)}
                                                        icon={<CheckCircle2 className="w-3.5 h-3.5" />}
                                                    >
                                                        Accepter
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => setReservationToRefuse(res)}
                                                        icon={<XCircle className="w-3.5 h-3.5 text-rose-600" />}
                                                    >
                                                        Refuser
                                                    </Button>
                                                </>
                                            )}

                                            {isPending && !isIntervenant && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleRetirer(res)}
                                                    icon={<XCircle className="w-3.5 h-3.5 text-slate-500" />}
                                                >
                                                    Retirer la demande
                                                </Button>
                                            )}

                                            {/* Séance acceptée */}
                                            {isAccepted && (
                                                <>
                                                    {!hasConfirmed && Date.now() >= end && (
                                                        <Button
                                                            variant="primary"
                                                            size="sm"
                                                            onClick={() => handleConfirmerSeance(res)}
                                                            icon={<CheckCircle2 className="w-3.5 h-3.5" />}
                                                        >
                                                            Confirmer la séance
                                                        </Button>
                                                    )}

                                                    {Date.now() < start && <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => setReservationToCancel(res)}
                                                    >
                                                        Annuler (RG10)
                                                    </Button>}

                                                    {Date.now() >= start && <Button
                                                        variant="danger"
                                                        size="sm"
                                                        onClick={() => setReservationForDispute(res)}
                                                        icon={<AlertTriangle className="w-3.5 h-3.5" />}
                                                    >
                                                        Litige (RG12)
                                                    </Button>}
                                                </>
                                            )}

                                            {/* Séance clôturée / réalisée */}
                                            {isCompleted && (
                                                <span className="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200">
                                                    Séance terminée et crédits transférés ✓ <Link className="underline" to="/app/evaluations">Évaluer</Link>
                                                </span>
                                            )}

                                            {/* Séance en litige */}
                                            {isDispute && (
                                                <span className="text-xs font-semibold text-amber-800 bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200">
                                                    Arbitrage en cours · <Link className="underline" to="/app/litiges">Consulter et répondre</Link>
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Pagination */}
                    {lastPage > 1 && (
                        <div className="pt-4 flex justify-center">
                            <Pagination
                                currentPage={currentPage}
                                lastPage={lastPage}
                                total={total}
                                onPageChange={(p) => chargerReservations(p)}
                            />
                        </div>
                    )}
                </>
            )}

            {/* Modal de Refus (RG09) */}
            <Modal
                isOpen={Boolean(reservationToRefuse)}
                onClose={() => !isRefusing && setReservationToRefuse(null)}
                title="Refuser la demande de réservation"
                size="md"
            >
                <div className="space-y-4">
                    <p className="text-xs text-slate-600">
                        Veuillez préciser la raison de votre refus afin d'en informer l'élève (indisponibilité, niveau inadapté...) :
                    </p>

                    <div>
                        <textarea
                            rows={3}
                            value={refusalMotif}
                            onChange={(e) => setRefusalMotif(e.target.value)}
                            placeholder="Motif du refus..."
                            className="w-full text-xs rounded-xl border border-slate-200 p-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 resize-none"
                            disabled={isRefusing}
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button
                            variant="outline"
                            onClick={() => setReservationToRefuse(null)}
                            disabled={isRefusing}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleConfirmerRefus}
                            disabled={refusalMotif.trim().length < 3}
                            isLoading={isRefusing}
                        >
                            Confirmer le refus
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Modal d'Annulation Pré-Séance (RG10) */}
            <Modal
                isOpen={Boolean(reservationToCancel)}
                onClose={() => !isCancelling && setReservationToCancel(null)}
                title="Annuler la séance (Règle RG10)"
                size="md"
            >
                <div className="space-y-4">
                    <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200/80 text-xs text-amber-900 leading-relaxed space-y-1">
                        <p className="font-semibold">Règle RG10 sur l'annulation avant le début :</p>
                        <p>
                            Une séance acceptée peut être annulée avant son horaire de démarrage.
                            Les crédits bloqués du bénéficiaire ({reservationToCancel?.cout_credits} crédit(s))
                            lui seront <strong>intégralement restitués</strong> sans pénalité.
                        </p>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Motif de l'annulation
                        </label>
                        <textarea
                            rows={3}
                            value={cancellationMotif}
                            onChange={(e) => setCancellationMotif(e.target.value)}
                            placeholder="Raison de l'annulation..."
                            className="w-full text-xs rounded-xl border border-slate-200 p-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 resize-none"
                            disabled={isCancelling}
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button
                            variant="outline"
                            onClick={() => setReservationToCancel(null)}
                            disabled={isCancelling}
                        >
                            Conserver la séance
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleConfirmerAnnulation}
                            isLoading={isCancelling}
                        >
                            Confirmer l'annulation (restitution des crédits)
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Modal de Litige (RG12) */}
            <Modal
                isOpen={Boolean(reservationForDispute)}
                onClose={() => !isSubmittingDispute && setReservationForDispute(null)}
                title="Déclarer un litige sur cette séance (RG12)"
                size="md"
            >
                <div className="space-y-4">
                    <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200/80 text-xs text-rose-900 leading-relaxed space-y-1">
                        <p className="font-semibold">Protection contre les abus & Arbitrage (RG12, RG13) :</p>
                        <p>
                            En cas de désaccord, absence injustifiée ou séance non conforme, l'ouverture d'un litige bloque tout transfert financier. Les crédits restent sous séquestre jusqu'à décision définitive de l'administration du campus.
                        </p>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Description précise du différend (10 caractères minimum)
                        </label>
                        <textarea
                            rows={4}
                            value={disputeDescription}
                            onChange={(e) => setDisputeDescription(e.target.value)}
                            placeholder="Expliquez ce qui s'est produit (intervenant absent, durée non respectée, matériel indisponible...)..."
                            className="w-full text-xs rounded-xl border border-slate-200 p-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-600 resize-none"
                            disabled={isSubmittingDispute}
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button
                            variant="outline"
                            onClick={() => setReservationForDispute(null)}
                            disabled={isSubmittingDispute}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleSoumettreLitige}
                            isLoading={isSubmittingDispute}
                            icon={<AlertTriangle className="w-3.5 h-3.5" />}
                        >
                            Ouvrir le litige
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
};
export default ReservationsPage;
