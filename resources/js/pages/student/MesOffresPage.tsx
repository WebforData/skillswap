/**
 * SkillSwap Campus — Page Mes Offres & Gestion des Créneaux (RG02, RG04, RG17)
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    Plus,
    Edit2,
    Archive,
    Upload,
    FileText,
    Calendar,
    Clock,
    MapPin,
    Lock,
    Trash2,
    Coins,
    CheckCircle2,
    Layers,
} from 'lucide-react';
import { offresApi } from '../../api/offres.api';
import { creneauxApi } from '../../api/creneaux.api';
import { useNotification } from '../../contexts/NotificationContext';
import { Offre, CreneauCreationPayload, NiveauOffre, ModeOffre } from '../../types/offre.types';
import { Button, Input, Select, Badge, Card, Modal, Spinner, EmptyState, AlertBanner } from '../../components/common';
import axios from 'axios';

// Catégories de référence
const CATEGORIES_REF = [
    { id: 1, nom: 'Informatique & Programmation' },
    { id: 2, nom: 'Langues & Communication' },
    { id: 3, nom: 'Mathématiques & Sciences' },
    { id: 4, nom: 'Méthodologie & Soft Skills' },
    { id: 5, nom: 'Design & Multimédia' },
];

export const MesOffresPage: React.FC = () => {
    const { success, error: notifyError } = useNotification();

    // État principal des offres
    const [offres, setOffres] = useState<Offre[]>([]);
    const [filtreStatut, setFiltreStatut] = useState<string>('tous');
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Modale Offre (Création / Modification)
    const [isOfferModalOpen, setIsOfferModalOpen] = useState<boolean>(false);
    const [editingOffer, setEditingOffer] = useState<Offre | null>(null);
    const [offerForm, setOfferForm] = useState({
        titre: '',
        competence: '',
        description: '',
        categorie_id: 1,
        niveau: 'debutant' as NiveauOffre,
        duree: 1, // 1 ou 2 (RG02)
        mode: 'presentiel' as ModeOffre,
    });
    const [isSavingOffer, setIsSavingOffer] = useState<boolean>(false);
    const [offerFormErrors, setOfferFormErrors] = useState<Record<string, string>>({});

    // Modale Gestion des Créneaux
    const [selectedOfferForSlots, setSelectedOfferForSlots] = useState<Offre | null>(null);
    const [isSlotModalOpen, setIsSlotModalOpen] = useState<boolean>(false);
    const [slotForm, setSlotForm] = useState<CreneauCreationPayload>({
        date: '',
        heure_debut: '14:00',
        heure_fin: '15:00',
        lieu_public: 'Bibliothèque Universitaire - Espace Travail',
        lien_ou_lieu_prive: 'Salle B204 (accès avec carte étudiant)',
    });
    const [editingSlotId, setEditingSlotId] = useState<number | null>(null);
    const [isSavingSlot, setIsSavingSlot] = useState<boolean>(false);
    const [slotFormErrors, setSlotFormErrors] = useState<Record<string, string>>({});
    const [slotGlobalError, setSlotGlobalError] = useState<string | null>(null);

    // Modale de confirmation d'archivage (RG17)
    const [offerToArchive, setOfferToArchive] = useState<Offre | null>(null);
    const [isArchiving, setIsArchiving] = useState<boolean>(false);

    // Chargement de mes offres
    const chargerMesOffres = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await offresApi.getMesOffres(1, 50);
            setOffres(data.data);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Impossible de charger vos offres.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        chargerMesOffres();
    }, [chargerMesOffres]);

    // Filtrage local par statut
    const offresFiltrees = offres.filter((offre) => {
        if (filtreStatut === 'tous') return true;
        return offre.statut === filtreStatut;
    });

    // Ouvrir la modale d'offre (création)
    const ouvrirCreationOffre = () => {
        setEditingOffer(null);
        setOfferForm({
            titre: '',
            competence: '',
            description: '',
            categorie_id: 1,
            niveau: 'debutant',
            duree: 1,
            mode: 'presentiel',
        });
        setOfferFormErrors({});
        setIsOfferModalOpen(true);
    };

    // Ouvrir la modale d'offre (modification)
    const ouvrirModificationOffre = (offre: Offre) => {
        setEditingOffer(offre);
        setOfferForm({
            titre: offre.titre,
            competence: offre.competence,
            description: offre.description,
            categorie_id: offre.categorie_id || 1,
            niveau: offre.niveau,
            duree: offre.duree,
            mode: offre.mode,
        });
        setOfferFormErrors({});
        setIsOfferModalOpen(true);
    };

    // Soumettre le formulaire d'offre
    const handleSauvegarderOffre = async (e: React.FormEvent) => {
        e.preventDefault();
        setOfferFormErrors({});
        setIsSavingOffer(true);

        try {
            if (editingOffer) {
                await offresApi.modifierOffre(editingOffer.id, offerForm);
                success('Offre mise à jour avec succès.');
            } else {
                await offresApi.creerOffre({ ...offerForm, statut: 'brouillon' });
                success('Nouvelle offre créée en statut brouillon.');
            }
            setIsOfferModalOpen(false);
            chargerMesOffres();
        } catch (err: any) {
            const resp = axios.isAxiosError(err) ? err.response : undefined;
            if (resp) {
                if (resp.status === 422 && resp.data?.errors) {
                    const mapped: Record<string, string> = {};
                    Object.keys(resp.data.errors).forEach((key) => {
                        mapped[key] = resp.data.errors[key][0];
                    });
                    setOfferFormErrors(mapped);
                } else {
                    notifyError(resp.data?.message || 'Erreur lors de la sauvegarde.');
                }
            } else {
                notifyError('Erreur réseau.');
            }
        } finally {
            setIsSavingOffer(false);
        }
    };

    // Changer le statut (publier / brouillon)
    const handlePublier = async (offreId: number) => {
        try {
            await offresApi.publierOffre(offreId);
            success('Offre publiée avec succès. Elle est désormais visible au catalogue.');
            chargerMesOffres();
        } catch (err: any) {
            notifyError(err?.response?.data?.message || 'Impossible de publier cette offre.');
        }
    };

    const handlePasserEnBrouillon = async (offreId: number) => {
        try {
            await offresApi.passerEnBrouillon(offreId);
            success("Offre retirée du catalogue public et repassée en brouillon.");
            chargerMesOffres();
        } catch (err: any) {
            notifyError(err?.response?.data?.message || 'Impossible de repasser cette offre en brouillon.');
        }
    };

    // Confirmer l'archivage (RG17)
    const handleConfirmerArchivage = async () => {
        if (!offerToArchive) return;
        setIsArchiving(true);
        try {
            await offresApi.archiverOffre(offerToArchive.id);
            success('Offre archivée avec succès (RG17). Les réservations en attente ont été annulées.');
            setOfferToArchive(null);
            chargerMesOffres();
        } catch (err: any) {
            notifyError(err?.response?.data?.message || "Erreur lors de l'archivage.");
        } finally {
            setIsArchiving(false);
        }
    };

    // Gérer les créneaux d'une offre
    const ouvrirGestionCreneaux = async (offre: Offre) => {
        setSelectedOfferForSlots(offre);
        setEditingSlotId(null);
        setSlotGlobalError(null);
        setSlotFormErrors({});

        // Calculer l'heure de fin suggérée selon la durée (1h ou 2h)
        const dateDefaut = new Date();
        dateDefaut.setDate(dateDefaut.getDate() + 2); // Dans 2 jours
        const dateIso = dateDefaut.toISOString().split('T')[0];

        const heureFinSuggeree = offre.duree === 2 ? '16:00' : '15:00';

        setSlotForm({
            date: dateIso,
            heure_debut: '14:00',
            heure_fin: heureFinSuggeree,
            lieu_public: offre.mode === 'distanciel' ? 'Visioconférence en ligne' : 'Campus Universitaire - Bibliothèque',
            lien_ou_lieu_prive: offre.mode === 'distanciel' ? 'https://meet.google.com/xyz-abcd-efg' : 'Salle d\'étude n°3, 1er étage',
        });

        setIsSlotModalOpen(true);
    };

    // Sauvegarder un créneau (Ajout ou Modification)
    const handleSauvegarderCreneau = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedOfferForSlots) return;

        setSlotFormErrors({});
        setSlotGlobalError(null);
        setIsSavingSlot(true);

        try {
            if (editingSlotId) {
                await creneauxApi.modifierCreneau(editingSlotId, slotForm);
                success('Créneau mis à jour.');
            } else {
                await creneauxApi.ajouterCreneau(selectedOfferForSlots.id, slotForm);
                success('Nouveau créneau ajouté avec succès.');
            }

            // Rafraîchir les créneaux de cette offre
            const refreshedOffer = await offresApi.getOffre(selectedOfferForSlots.id);
            setSelectedOfferForSlots(refreshedOffer.offre);
            chargerMesOffres();

            // Réinitialiser le formulaire
            setEditingSlotId(null);
        } catch (err: any) {
            const resp = axios.isAxiosError(err) ? err.response : undefined;
            if (resp) {
                if (resp.status === 422 && resp.data?.errors) {
                    const mapped: Record<string, string> = {};
                    Object.keys(resp.data.errors).forEach((key) => {
                        mapped[key] = resp.data.errors[key][0];
                    });
                    setSlotFormErrors(mapped);
                } else {
                    setSlotGlobalError(resp.data?.message || 'Erreur lors de la sauvegarde du créneau.');
                }
            } else {
                setSlotGlobalError('Erreur de connexion.');
            }
        } finally {
            setIsSavingSlot(false);
        }
    };

    // Supprimer un créneau
    const handleSupprimerCreneau = async (creneauId: number) => {
        if (!selectedOfferForSlots) return;
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce créneau ?')) return;

        try {
            await creneauxApi.supprimerCreneau(creneauId);
            success('Créneau supprimé.');
            const refreshedOffer = await offresApi.getOffre(selectedOfferForSlots.id);
            setSelectedOfferForSlots(refreshedOffer.offre);
            chargerMesOffres();
        } catch (err: any) {
            notifyError(err?.response?.data?.message || 'Impossible de supprimer ce créneau.');
        }
    };

    return (
        <div className="space-y-6">
            {/* En-tête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">
                        Mes Compétences & Offres Proposées
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Proposez des séances d'aide, définissez vos créneaux et gagnez des crédits pour apprendre d'autres matières.
                    </p>
                </div>

                <Button
                    variant="primary"
                    onClick={ouvrirCreationOffre}
                    icon={<Plus className="w-4 h-4" />}
                >
                    Proposer une compétence
                </Button>
            </div>

            {/* Filtres par statut */}
            <div className="flex flex-wrap items-center gap-2">
                {[
                    { id: 'tous', label: 'Toutes les offres' },
                    { id: 'publiee', label: 'Publiées' },
                    { id: 'brouillon', label: 'Brouillons' },
                    { id: 'archivee', label: 'Archivées' },
                ].map((f) => (
                    <button
                        key={f.id}
                        type="button"
                        onClick={() => setFiltreStatut(f.id)}
                        className={`
                            px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all
                            ${
                                filtreStatut === f.id
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
                            }
                        `}
                    >
                        {f.label}
                    </button>
                ))}
            </div>

            {/* État de chargement / Erreur */}
            {isLoading && (
                <div className="py-20 flex flex-col items-center justify-center">
                    <Spinner size="lg" />
                    <p className="mt-3 text-xs text-slate-500">Chargement de vos offres...</p>
                </div>
            )}

            {error && (
                <AlertBanner variant="error" message={error} onClose={() => setError(null)} />
            )}

            {/* Liste des offres */}
            {!isLoading && (
                <>
                    {offresFiltrees.length === 0 ? (
                        <EmptyState
                            icon={<Layers className="w-10 h-10" />}
                            title="Aucune offre dans cette section"
                            description={
                                filtreStatut === 'tous'
                                    ? "Vous n'avez pas encore créé d'offre de compétence. Commencez dès maintenant à partager votre savoir !"
                                    : `Aucune offre ne possède actuellement le statut « ${filtreStatut} ».`
                            }
                            action={
                                <Button variant="primary" onClick={ouvrirCreationOffre} icon={<Plus className="w-4 h-4" />}>
                                    Créer ma première offre
                                </Button>
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {offresFiltrees.map((offre) => {
                                const isArchived = offre.statut === 'archivee';
                                const isPublished = offre.statut === 'publiee';
                                const isDraft = offre.statut === 'brouillon';

                                return (
                                    <Card key={offre.id} className="flex flex-col h-full border-slate-200/90 hover:border-slate-300">
                                        <div className="flex items-start justify-between gap-3 mb-2">
                                            <div className="flex items-center gap-2">
                                                <Badge status={offre.statut} />
                                                <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                                    {offre.categorie?.nom || 'Général'}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60">
                                                <Coins className="w-3.5 h-3.5" />
                                                <span>{offre.duree} crédit{offre.duree > 1 ? 's' : ''} (RG02)</span>
                                            </div>
                                        </div>

                                        <h3 className="text-base font-bold text-slate-900 mb-1">
                                            {offre.titre}
                                        </h3>

                                        <p className="text-xs font-medium text-indigo-700 bg-indigo-50/60 px-2.5 py-1 rounded-lg mb-2">
                                            Compétence : <strong className="text-slate-900">{offre.competence}</strong>
                                        </p>

                                        <p className="text-xs text-slate-600 line-clamp-2 mb-4 flex-1">
                                            {offre.description}
                                        </p>

                                        {/* Créneaux rattachés */}
                                        <div className="p-3 rounded-xl bg-slate-50 border border-slate-200/70 mb-4 flex items-center justify-between">
                                            <div className="flex items-center gap-2 text-xs text-slate-600">
                                                <Calendar className="w-4 h-4 text-indigo-600" />
                                                <span>
                                                    <strong>{offre.creneaux?.length ?? 0}</strong> créneau(x) configuré(s)
                                                </span>
                                            </div>

                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => ouvrirGestionCreneaux(offre)}
                                                disabled={isArchived}
                                                icon={<Clock className="w-3.5 h-3.5" />}
                                            >
                                                Gérer les créneaux
                                            </Button>
                                        </div>

                                        {/* Actions sur l'offre */}
                                        <div className="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                                            <div className="flex items-center gap-2">
                                                {!isArchived && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => ouvrirModificationOffre(offre)}
                                                        icon={<Edit2 className="w-3.5 h-3.5" />}
                                                    >
                                                        Modifier
                                                    </Button>
                                                )}

                                                {isDraft && (
                                                    <Button
                                                        variant="primary"
                                                        size="sm"
                                                        onClick={() => handlePublier(offre.id)}
                                                        icon={<Upload className="w-3.5 h-3.5" />}
                                                    >
                                                        Publier
                                                    </Button>
                                                )}

                                                {isPublished && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handlePasserEnBrouillon(offre.id)}
                                                        icon={<FileText className="w-3.5 h-3.5" />}
                                                    >
                                                        Brouillon
                                                    </Button>
                                                )}
                                            </div>

                                            {!isArchived && (
                                                <Button
                                                    variant="danger"
                                                    size="sm"
                                                    onClick={() => setOfferToArchive(offre)}
                                                    icon={<Archive className="w-3.5 h-3.5" />}
                                                >
                                                    Archiver
                                                </Button>
                                            )}
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </>
            )}

            {/* Modal de Création / Modification d'une Offre */}
            <Modal
                isOpen={isOfferModalOpen}
                onClose={() => !isSavingOffer && setIsOfferModalOpen(false)}
                title={editingOffer ? "Modifier l'offre de compétence" : "Proposer une nouvelle compétence"}
                size="lg"
            >
                <form onSubmit={handleSauvegarderOffre} className="space-y-4">
                    <Input
                        id="offer-titre"
                        label="Titre de l'offre"
                        value={offerForm.titre}
                        onChange={(e) => setOfferForm({ ...offerForm, titre: e.target.value })}
                        placeholder="Ex : Cours particulier de Python et Algorithmique L1"
                        error={offerFormErrors.titre}
                        helperText="Minimum 5 caractères. Titre descriptif et engageant."
                        required
                        disabled={isSavingOffer}
                    />

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            id="offer-competence"
                            label="Compétence principale enseignée"
                            value={offerForm.competence}
                            onChange={(e) => setOfferForm({ ...offerForm, competence: e.target.value })}
                            placeholder="Ex : Python, SQL, Statistiques..."
                            error={offerFormErrors.competence}
                            required
                            disabled={isSavingOffer}
                        />

                        <Select
                            id="offer-categorie"
                            label="Catégorie universitaire"
                            value={offerForm.categorie_id}
                            onChange={(e) => setOfferForm({ ...offerForm, categorie_id: Number(e.target.value) })}
                            options={CATEGORIES_REF.map((c) => ({ value: c.id, label: c.nom }))}
                            error={offerFormErrors.categorie_id}
                            disabled={isSavingOffer}
                        />
                    </div>

                    <div>
                        <label htmlFor="offer-desc" className="block text-xs font-semibold text-slate-700 mb-1">
                            Description détaillée du cours
                        </label>
                        <textarea
                            id="offer-desc"
                            rows={4}
                            value={offerForm.description}
                            onChange={(e) => setOfferForm({ ...offerForm, description: e.target.value })}
                            placeholder="Présentez les notions abordées, les prérequis éventuels, votre méthode pédagogique..."
                            className="w-full text-xs rounded-xl border border-slate-200 bg-white p-3 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all resize-none"
                            disabled={isSavingOffer}
                        />
                        {offerFormErrors.description && (
                            <p className="text-[11px] text-rose-600 mt-1">{offerFormErrors.description}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <Select
                            id="offer-niveau"
                            label="Niveau requis"
                            value={offerForm.niveau}
                            onChange={(e) => setOfferForm({ ...offerForm, niveau: e.target.value as NiveauOffre })}
                            options={[
                                { value: 'debutant', label: 'Débutant' },
                                { value: 'intermediaire', label: 'Intermédiaire' },
                                { value: 'avance', label: 'Avancé' },
                            ]}
                            disabled={isSavingOffer}
                        />

                        <Select
                            id="offer-duree"
                            label="Durée de la séance (RG02)"
                            value={offerForm.duree}
                            onChange={(e) => setOfferForm({ ...offerForm, duree: Number(e.target.value) })}
                            options={[
                                { value: 1, label: '1 heure (1 crédit)' },
                                { value: 2, label: '2 heures (2 crédits)' },
                            ]}
                            disabled={isSavingOffer}
                        />

                        <Select
                            id="offer-mode"
                            label="Modalité de la séance"
                            value={offerForm.mode}
                            onChange={(e) => setOfferForm({ ...offerForm, mode: e.target.value as ModeOffre })}
                            options={[
                                { value: 'presentiel', label: 'Présentiel (Campus)' },
                                { value: 'distanciel', label: 'Distanciel (Visio)' },
                            ]}
                            disabled={isSavingOffer}
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsOfferModalOpen(false)}
                            disabled={isSavingOffer}
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={isSavingOffer}
                        >
                            {editingOffer ? 'Mettre à jour' : "Créer l'offre"}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal de Gestion des Créneaux d'une Offre */}
            <Modal
                isOpen={isSlotModalOpen}
                onClose={() => setIsSlotModalOpen(false)}
                title={`Gestion des créneaux — ${selectedOfferForSlots?.titre || ''}`}
                size="xl"
            >
                <div className="space-y-6">
                    {slotGlobalError && (
                        <AlertBanner variant="error" message={slotGlobalError} onClose={() => setSlotGlobalError(null)} />
                    )}

                    {/* Rappel RG19 */}
                    <div className="p-3 rounded-xl bg-sky-50 border border-sky-200/80 text-xs text-sky-900 leading-relaxed flex items-start gap-2">
                        <Lock className="w-4 h-4 text-sky-700 shrink-0 mt-0.5" />
                        <span>
                            <strong>Protection RG19 : </strong>
                            Le <em>lieu public</em> est visible par tous les étudiants au catalogue. Le <em>lien ou lieu privé</em> (salle exacte, lien Meet/Zoom) ne sera communiqué au bénéficiaire qu'une fois la réservation validée.
                        </span>
                    </div>

                    {/* Formulaire d'ajout / modification de créneau */}
                    <form onSubmit={handleSauvegarderCreneau} className="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 space-y-3">
                        <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            {editingSlotId ? 'Modifier le créneau sélectionné' : 'Ajouter un nouveau créneau disponible'}
                        </h4>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <Input
                                id="slot-date"
                                label="Date de la séance"
                                type="date"
                                value={slotForm.date}
                                onChange={(e) => setSlotForm({ ...slotForm, date: e.target.value })}
                                error={slotFormErrors.date}
                                required
                                disabled={isSavingSlot}
                            />

                            <Input
                                id="slot-debut"
                                label="Heure de début"
                                type="time"
                                value={slotForm.heure_debut}
                                onChange={(e) => setSlotForm({ ...slotForm, heure_debut: e.target.value })}
                                error={slotFormErrors.heure_debut}
                                required
                                disabled={isSavingSlot}
                            />

                            <Input
                                id="slot-fin"
                                label="Heure de fin"
                                type="time"
                                value={slotForm.heure_fin}
                                onChange={(e) => setSlotForm({ ...slotForm, heure_fin: e.target.value })}
                                error={slotFormErrors.heure_fin}
                                required
                                disabled={isSavingSlot}
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Input
                                id="slot-public"
                                label="Lieu public indicatif (catalogue)"
                                value={slotForm.lieu_public}
                                onChange={(e) => setSlotForm({ ...slotForm, lieu_public: e.target.value })}
                                placeholder="Ex : Bibliothèque Centrale, Visio..."
                                error={slotFormErrors.lieu_public}
                                required
                                disabled={isSavingSlot}
                            />

                            <Input
                                id="slot-prive"
                                label="Lien ou lieu privé exact (RG19)"
                                value={slotForm.lien_ou_lieu_prive}
                                onChange={(e) => setSlotForm({ ...slotForm, lien_ou_lieu_prive: e.target.value })}
                                placeholder="Ex : Salle 204, Lien Google Meet..."
                                error={slotFormErrors.lien_ou_lieu_prive}
                                required
                                disabled={isSavingSlot}
                            />
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-2">
                            {editingSlotId && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditingSlotId(null)}
                                    disabled={isSavingSlot}
                                >
                                    Annuler la modification
                                </Button>
                            )}

                            <Button
                                type="submit"
                                variant="primary"
                                size="sm"
                                isLoading={isSavingSlot}
                                icon={<CheckCircle2 className="w-3.5 h-3.5" />}
                            >
                                {editingSlotId ? 'Valider les modifications' : 'Enregistrer ce créneau'}
                            </Button>
                        </div>
                    </form>

                    {/* Liste des créneaux existants */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Créneaux programmés pour cette offre ({selectedOfferForSlots?.creneaux?.length ?? 0})
                        </h4>

                        {!selectedOfferForSlots?.creneaux || selectedOfferForSlots.creneaux.length === 0 ? (
                            <p className="text-xs text-slate-500 italic p-4 text-center border border-dashed rounded-xl">
                                Aucun créneau pour le moment. Remplissez le formulaire ci-dessus pour ouvrir une disponibilité.
                            </p>
                        ) : (
                            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
                                {selectedOfferForSlots.creneaux.map((creneau) => (
                                    <div
                                        key={creneau.id}
                                        className="bg-white border border-slate-200 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-slate-900">{creneau.date}</span>
                                                <span className="font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md">
                                                    {creneau.heure_debut.substring(0, 5)} &mdash; {creneau.heure_fin.substring(0, 5)}
                                                </span>
                                            </div>
                                            <div className="text-slate-500 flex items-center gap-1.5">
                                                <MapPin className="w-3 h-3 text-slate-400" />
                                                <span>Lieu public : {creneau.lieu_public}</span>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2 shrink-0">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setEditingSlotId(creneau.id);
                                                    setSlotForm({
                                                        date: creneau.date,
                                                        heure_debut: creneau.heure_debut.substring(0, 5),
                                                        heure_fin: creneau.heure_fin.substring(0, 5),
                                                        lieu_public: creneau.lieu_public,
                                                        lien_ou_lieu_prive: creneau.lieu_ou_lien_prive || '',
                                                    });
                                                }}
                                                icon={<Edit2 className="w-3 h-3" />}
                                            >
                                                Éditer
                                            </Button>

                                            <Button
                                                variant="danger"
                                                size="sm"
                                                onClick={() => handleSupprimerCreneau(creneau.id)}
                                                icon={<Trash2 className="w-3 h-3" />}
                                            >
                                                Supprimer
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </Modal>

            {/* Modal de Confirmation d'Archivage (RG17) */}
            <Modal
                isOpen={Boolean(offerToArchive)}
                onClose={() => !isArchiving && setOfferToArchive(null)}
                title="Confirmer l'archivage de l'offre (RG17)"
                size="md"
            >
                <div className="space-y-4">
                    <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200/80 text-xs text-amber-900 leading-relaxed space-y-2">
                        <p className="font-semibold">Attention : règle RG17 sur l'archivage :</p>
                        <ul className="list-disc list-inside space-y-1">
                            <li>L'offre ne sera plus visible au catalogue et ne pourra plus recevoir de demandes.</li>
                            <li>Toutes les demandes actuellement <strong>en attente</strong> seront automatiquement annulées.</li>
                            <li>Les séances <strong>déjà acceptées ou en cours</strong> restent strictement maintenues et leurs crédits garantis.</li>
                        </ul>
                    </div>

                    <p className="text-xs text-slate-600">
                        Voulez-vous vraiment archiver l'offre :{' '}
                        <strong className="text-slate-900">{offerToArchive?.titre}</strong> ?
                    </p>

                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button
                            variant="outline"
                            onClick={() => setOfferToArchive(null)}
                            disabled={isArchiving}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleConfirmerArchivage}
                            isLoading={isArchiving}
                            icon={<Archive className="w-4 h-4" />}
                        >
                            Confirmer l'archivage
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
};
export default MesOffresPage;
