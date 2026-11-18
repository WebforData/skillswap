/**
 * SkillSwap Campus — Détail d'une Offre & Demande de Réservation (RG01 à RG05, RG08, RG19)
 */

import React, { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate, useLocation } from 'react-router-dom';
import {
    ArrowLeft,
    Clock,
    Calendar,
    MapPin,
    Laptop,
    Coins,
    ShieldCheck,
    Star,
    CheckCircle2,
    Info,
    Sparkles,
} from 'lucide-react';
import { offresApi } from '../../api/offres.api';
import { reservationsApi } from '../../api/reservations.api';
import { evaluationsApi } from '../../api/evaluations.api';
import { useAuth } from '../../hooks/useAuth';
import { useNotification } from '../../contexts/NotificationContext';
import { Offre, Creneau } from '../../types/offre.types';
import { EvaluationStats } from '../../types/evaluation.types';
import {
    Button,
    Badge,
    Card,
    Modal,
    Spinner,
    EmptyState,
    AlertBanner,
} from '../../components/common';
import axios from 'axios';

export const OffreDetailPage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const offreId = Number(id);

    const { user, isAuthenticated } = useAuth();
    const { success } = useNotification();
    const navigate = useNavigate();
    const location = useLocation();

    // État principal
    const [offre, setOffre] = useState<Offre | null>(null);
    const [intervenantStats, setIntervenantStats] = useState<EvaluationStats | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // État de la réservation
    const [selectedCreneau, setSelectedCreneau] = useState<Creneau | null>(null);
    const [isBookingModalOpen, setIsBookingModalOpen] = useState<boolean>(false);
    const [isSubmittingBooking, setIsSubmittingBooking] = useState<boolean>(false);
    const [bookingError, setBookingError] = useState<string | null>(null);
    const [bookingSuccessMessage, setBookingSuccessMessage] = useState<string | null>(null);

    // Chargement de l'offre
    const chargerOffre = useCallback(async () => {
        if (!offreId || isNaN(offreId)) {
            setError("Identifiant d'offre invalide.");
            setIsLoading(false);
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const data = await offresApi.getOffre(offreId);
            setOffre(data.offre);

            // Charger les statistiques d'évaluation de l'intervenant si disponible
            if (data.offre.intervenant?.id) {
                try {
                    const stats = await evaluationsApi.getStats(data.offre.intervenant.id);
                    setIntervenantStats(stats);
                } catch {
                    // Statistiques optionnelles, ne pas bloquer l'affichage de l'offre
                }
            }
        } catch (err: any) {
            if (axios.isAxiosError(err) && err.response?.status === 404) {
                setError("Cette offre d'échange est introuvable ou a été supprimée.");
            } else if (axios.isAxiosError(err) && err.response?.status === 403) {
                setError("Cette offre n'est plus accessible au public (RG04).");
            } else {
                setError("Impossible de charger les détails de l'offre. Veuillez réessayer.");
            }
        } finally {
            setIsLoading(false);
        }
    }, [offreId]);

    useEffect(() => {
        chargerOffre();
    }, [chargerOffre]);

    // Formatage de la date en français
    const formaterDateFr = (dateStr: string) => {
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        } catch {
            return dateStr;
        }
    };

    // Formatage des heures (ex: "14:00")
    const formaterHeure = (heureStr: string) => {
        return heureStr.substring(0, 5);
    };

    // Ouverture de la modale de réservation
    const ouvrirModalReservation = (creneau: Creneau) => {
        if (!isAuthenticated) {
            navigate('/connexion', { state: { from: location } });
            return;
        }
        setSelectedCreneau(creneau);
        setBookingError(null);
        setBookingSuccessMessage(null);
        setIsBookingModalOpen(true);
    };

    // Soumission de la demande de réservation
    const handleConfirmerReservation = async () => {
        if (!selectedCreneau) return;

        setIsSubmittingBooking(true);
        setBookingError(null);

        try {
            const res = await reservationsApi.demanderReservation(selectedCreneau.id);
            setBookingSuccessMessage(res.message);
            success("Votre demande de réservation a été enregistrée avec succès (statut : en attente).");

            // Rafraîchir l'offre après un court délai pour actualiser les créneaux disponibles
            setTimeout(() => {
                setIsBookingModalOpen(false);
                setSelectedCreneau(null);
                chargerOffre();
            }, 1200);
        } catch (err: any) {
            if (axios.isAxiosError(err) && err.response) {
                const data = err.response.data;
                const status = err.response.status;

                if (status === 422) {
                    setBookingError(data.message || "Impossible d'effectuer cette réservation.");
                } else if (status === 403) {
                    setBookingError(
                        data.message || "Votre compte est désactivé (RG18). Réservation impossible."
                    );
                } else {
                    setBookingError(data.message || "Une erreur est survenue lors de la réservation.");
                }
            } else {
                setBookingError("Erreur réseau. Impossible de contacter le serveur.");
            }
        } finally {
            setIsSubmittingBooking(false);
        }
    };

    // Vérifications métier côté client (pour guider l'utilisateur)
    const isOwner = user && offre && user.id === offre.etudiant_id;
    const soldeDisponible = user?.portefeuille?.solde_disponible ?? user?.solde_disponible ?? 0;
    const coutCredits = offre?.duree ?? 1;
    const soldeSuffisant = soldeDisponible >= coutCredits;

    if (isLoading) {
        return (
            <div className="py-24 flex flex-col items-center justify-center">
                <Spinner size="lg" />
                <p className="mt-3 text-sm text-slate-500">Chargement des détails de l'offre...</p>
            </div>
        );
    }

    if (error || !offre) {
        return (
            <div className="py-12 max-w-2xl mx-auto space-y-4">
                <AlertBanner
                    variant="error"
                    title="Offre indisponible"
                    message={error || "L'offre demandée n'existe pas."}
                />
                <div className="text-center pt-4">
                    <Link to="/catalogue">
                        <Button variant="outline" icon={<ArrowLeft className="w-4 h-4" />}>
                            Retourner au catalogue
                        </Button>
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="max-w-5xl mx-auto space-y-8">
            {/* Lien retour */}
            <div>
                <Link
                    to="/catalogue"
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-indigo-600 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    <span>Retour au catalogue des offres</span>
                </Link>
            </div>

            {/* Bannière de l'offre */}
            <div className="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-6">
                    <div className="space-y-3 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            {offre.categorie && (
                                <span className="text-xs font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-200/60 px-3 py-1 rounded-lg">
                                    {offre.categorie.nom}
                                </span>
                            )}
                            <Badge status={offre.niveau} />
                            <span className="inline-flex items-center gap-1 text-xs font-medium text-slate-700 bg-slate-100 px-2.5 py-1 rounded-full">
                                {offre.mode === 'presentiel' ? (
                                    <>
                                        <MapPin className="w-3.5 h-3.5 text-emerald-600" />
                                        <span>Séance en Présentiel</span>
                                    </>
                                ) : (
                                    <>
                                        <Laptop className="w-3.5 h-3.5 text-sky-600" />
                                        <span>Séance en Distanciel</span>
                                    </>
                                )}
                            </span>
                        </div>

                        <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            {offre.titre}
                        </h1>

                        <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-700">
                            <Sparkles className="w-4 h-4 text-indigo-600" />
                            <span>
                                Compétence transmise : <strong className="text-slate-900">{offre.competence}</strong>
                            </span>
                        </div>
                    </div>

                    {/* Bloc Coût & Durée */}
                    <div className="shrink-0 bg-slate-50 border border-slate-200/80 rounded-2xl p-4 sm:p-5 flex lg:flex-col items-center justify-between gap-4 text-center">
                        <div>
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                Coût de la séance (RG01/RG02)
                            </span>
                            <div className="inline-flex items-center gap-2 text-2xl font-black text-amber-700">
                                <Coins className="w-6 h-6 text-amber-500" />
                                <span>{offre.duree} crédit{offre.duree > 1 ? 's' : ''}</span>
                            </div>
                        </div>

                        <div className="text-xs text-slate-500 flex items-center gap-1.5 pt-1">
                            <Clock className="w-4 h-4 text-slate-400" />
                            <span>Durée : {offre.duree} heure{offre.duree > 1 ? 's' : ''}</span>
                        </div>
                    </div>
                </div>

                {/* Description */}
                <div className="mt-8 pt-6 border-t border-slate-100">
                    <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2">
                        Description du cours & Objectifs
                    </h2>
                    <p className="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
                        {offre.description}
                    </p>
                </div>
            </div>

            {/* Fiche Intervenant & Avis */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card title="À propos de l'intervenant" className="md:col-span-1">
                    <div className="space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                                {offre.intervenant?.prenom?.charAt(0) || 'E'}
                            </div>
                            <div>
                                <h3 className="font-bold text-slate-900 text-sm">
                                    {offre.intervenant?.prenom} {offre.intervenant?.nom}
                                </h3>
                                <p className="text-xs text-slate-500">
                                    {offre.intervenant?.filiere || 'Étudiant certifié'}
                                </p>
                            </div>
                        </div>

                        {/* Évaluations */}
                        <div className="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1.5">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-slate-600">Note moyenne</span>
                                <div className="flex items-center gap-1 font-bold text-slate-900">
                                    <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                                    <span>
                                        {intervenantStats?.note_moyenne
                                            ? `${intervenantStats.note_moyenne} / 5`
                                            : 'Nouveau (pas encore noté)'}
                                    </span>
                                </div>
                            </div>
                            <div className="text-[11px] text-slate-500">
                                {intervenantStats?.nombre_evaluations
                                    ? `${intervenantStats.nombre_evaluations} avis reçu(s)`
                                    : 'Premiers échanges sur la plateforme'}
                            </div>
                        </div>

                        {isOwner && (
                            <div className="p-2.5 rounded-lg bg-indigo-50 border border-indigo-200 text-xs text-indigo-800 flex items-center gap-2">
                                <Info className="w-4 h-4 shrink-0" />
                                <span>Vous êtes l'auteur de cette offre d'échange.</span>
                            </div>
                        )}
                    </div>
                </Card>

                {/* Section Créneaux Disponibles */}
                <div className="md:col-span-2 space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Calendar className="w-5 h-5 text-indigo-600" />
                            <h2 className="text-base font-bold text-slate-900">
                                Créneaux disponibles pour réservation
                            </h2>
                        </div>
                        <span className="text-xs font-semibold text-slate-500">
                            {offre.creneaux?.length || 0} créneau(x) ouvert(s)
                        </span>
                    </div>

                    {/* Rappel RG19 */}
                    <div className="p-3.5 rounded-xl bg-sky-50 border border-sky-200/80 flex items-start gap-3">
                        <ShieldCheck className="w-4 h-4 text-sky-700 shrink-0 mt-0.5" />
                        <div className="text-xs text-sky-900 leading-relaxed">
                            <span className="font-semibold">Protection de la vie privée (RG19) : </span>
                            Les coordonnées précises (salle de cours, bibliothèque ou lien de visioconférence)
                            sont strictement protégées. Elles ne seront révélées qu'une fois votre réservation
                            validée par l'intervenant.
                        </div>
                    </div>

                    {/* Liste des créneaux */}
                    {!offre.creneaux || offre.creneaux.length === 0 ? (
                        <EmptyState
                            icon={<Calendar className="w-8 h-8" />}
                            title="Aucun créneau ouvert"
                            description="L'intervenant n'a pas encore programmé de créneaux futurs pour cette offre. Revenez consulter cette page ultérieurement."
                        />
                    ) : (
                        <div className="space-y-3">
                            {offre.creneaux.map((creneau) => (
                                <div
                                    key={creneau.id}
                                    className="bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 transition-colors shadow-2xs"
                                >
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-bold text-slate-900 capitalize">
                                                {formaterDateFr(creneau.date)}
                                            </span>
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md">
                                                <Clock className="w-3 h-3" />
                                                <span>
                                                    {formaterHeure(creneau.heure_debut)} &mdash; {formaterHeure(creneau.heure_fin)}
                                                </span>
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-1.5 text-xs text-slate-500">
                                            <MapPin className="w-3.5 h-3.5 text-slate-400" />
                                            <span>Lieu public : {creneau.lieu_public || 'À définir'}</span>
                                        </div>
                                    </div>

                                    <div>
                                        {isOwner ? (
                                            <Button variant="ghost" size="sm" disabled>
                                                Votre offre
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="primary"
                                                size="sm"
                                                onClick={() => ouvrirModalReservation(creneau)}
                                                icon={<Calendar className="w-3.5 h-3.5" />}
                                            >
                                                Réserver
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Modal de Demande de Réservation */}
            <Modal
                isOpen={isBookingModalOpen}
                onClose={() => {
                    if (!isSubmittingBooking) {
                        setIsBookingModalOpen(false);
                        setSelectedCreneau(null);
                    }
                }}
                title="Confirmer votre demande de réservation"
                size="md"
            >
                {selectedCreneau && (
                    <div className="space-y-5">
                        {bookingError && (
                            <AlertBanner
                                variant="error"
                                message={bookingError}
                                onClose={() => setBookingError(null)}
                            />
                        )}

                        {bookingSuccessMessage && (
                            <AlertBanner
                                variant="success"
                                message={bookingSuccessMessage}
                            />
                        )}

                        {/* Récapitulatif séance */}
                        <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                            <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span className="text-slate-500 font-medium">Cours :</span>
                                <span className="font-bold text-slate-900">{offre.titre}</span>
                            </div>
                            <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span className="text-slate-500 font-medium">Intervenant :</span>
                                <span className="font-semibold text-slate-800">
                                    {offre.intervenant?.prenom} {offre.intervenant?.nom}
                                </span>
                            </div>
                            <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span className="text-slate-500 font-medium">Date & Horaires :</span>
                                <span className="font-semibold text-slate-800 capitalize">
                                    {formaterDateFr(selectedCreneau.date)} ({formaterHeure(selectedCreneau.heure_debut)} - {formaterHeure(selectedCreneau.heure_fin)})
                                </span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-500 font-medium">Coût de la séance :</span>
                                <span className="inline-flex items-center gap-1 font-bold text-amber-700">
                                    <Coins className="w-3.5 h-3.5" />
                                    <span>{coutCredits} crédit{coutCredits > 1 ? 's' : ''} (RG01/RG02)</span>
                                </span>
                            </div>
                        </div>

                        {/* Information de Solde Étudiant */}
                        <div className="flex items-center justify-between p-3 rounded-xl bg-amber-50 border border-amber-200/80 text-xs text-amber-900">
                            <span className="font-semibold">Votre solde disponible :</span>
                            <span className="font-bold text-amber-950">
                                {soldeDisponible} crédit{soldeDisponible > 1 ? 's' : ''}
                            </span>
                        </div>

                        {!soldeSuffisant && (
                            <AlertBanner
                                variant="warning"
                                message={`Votre solde disponible (${soldeDisponible} crédit(s)) est inférieur au coût de la séance (${coutCredits} crédit(s)). Votre demande sera rejetée par le backend (RG05).`}
                            />
                        )}

                        {/* Explication RG05 */}
                        <div className="p-3 rounded-xl bg-slate-100 text-[11px] text-slate-600 leading-relaxed flex items-start gap-2">
                            <Info className="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" />
                            <span>
                                <strong>Règle RG05 : </strong>
                                Aucun crédit n'est prélevé ou bloqué lors de l'envoi de cette demande.
                                Vos crédits ne seront bloqués que si l'intervenant valide et accepte
                                la séance. Vous pouvez annuler votre demande sans pénalité tant qu'elle
                                est en attente.
                            </span>
                        </div>

                        {/* Actions de la modale */}
                        <div className="flex items-center justify-end gap-2 pt-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setIsBookingModalOpen(false);
                                    setSelectedCreneau(null);
                                }}
                                disabled={isSubmittingBooking}
                            >
                                Annuler
                            </Button>

                            <Button
                                variant="primary"
                                onClick={handleConfirmerReservation}
                                isLoading={isSubmittingBooking}
                                disabled={isSubmittingBooking || Boolean(bookingSuccessMessage)}
                                icon={<CheckCircle2 className="w-4 h-4" />}
                            >
                                Confirmer la demande
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
};
export default OffreDetailPage;
