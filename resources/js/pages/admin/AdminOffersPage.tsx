/**
 * SkillSwap Campus — Back-Office : Modération des Offres (Module D - RG17)
 */

import React, { useEffect, useState, useCallback } from 'react';
import {
    Layers,
    Search,
    Archive,
    Eye,
    RefreshCw,
    User as UserIcon,
    Calendar,
    Clock,
    Tag,
    AlertCircle,
} from 'lucide-react';
import { adminApi } from '../../api/admin.api';
import { Offre, Categorie } from '../../types/offre.types';
import Button from '../../components/common/Button';
import Badge from '../../components/common/Badge';
import AlertBanner from '../../components/common/AlertBanner';
import Modal from '../../components/common/Modal';
import Spinner from '../../components/common/Spinner';
import Card from '../../components/common/Card';

export const AdminOffersPage: React.FC = () => {
    const [offres, setOffres] = useState<Offre[]>([]);
    const [categories, setCategories] = useState<Categorie[]>([]);
    const [total, setTotal] = useState(0);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    // Filtres
    const [search, setSearch] = useState('');
    const [statutFilter, setStatutFilter] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');

    // Détail Offre
    const [selectedOffre, setSelectedOffre] = useState<Offre | null>(null);

    // Confirmation Archivage (RG17)
    const [offreToArchive, setOffreToArchive] = useState<Offre | null>(null);
    const [isActionLoading, setIsActionLoading] = useState(false);

    const chargerCategories = useCallback(async () => {
        try {
            const data = await adminApi.getCategories();
            setCategories(data.categories || []);
        } catch {
            // Ignorer silencieusement si la liste échoue
        }
    }, []);

    const chargerOffres = useCallback(async (page = 1) => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await adminApi.getOffres({
                page,
                q: search.trim() || undefined,
                statut: statutFilter || undefined,
                categorie_id: categoryFilter || undefined,
            });
            setOffres(data.offres || []);
            setTotal(data.total);
            setCurrentPage(data.current_page);
            setLastPage(data.last_page);
        } catch {
            setError("Impossible de charger les offres pour modération.");
        } finally {
            setIsLoading(false);
        }
    }, [search, statutFilter, categoryFilter]);

    useEffect(() => {
        chargerCategories();
    }, [chargerCategories]);

    useEffect(() => {
        const timer = setTimeout(() => {
            chargerOffres(1);
        }, 300);
        return () => clearTimeout(timer);
    }, [chargerOffres]);

    // Voir le détail d'une offre
    const handleViewOffre = async (id: number) => {
        try {
            const data = await adminApi.getOffre(id);
            setSelectedOffre(data.offre);
        } catch {
            setError("Erreur lors de la récupération des détails de l'offre.");
        }
    };

    // Confirmer archivage modérateur (RG17)
    const handleConfirmArchive = async () => {
        if (!offreToArchive) return;

        setIsActionLoading(true);
        setError(null);
        try {
            const res = await adminApi.archiverOffre(offreToArchive.id);
            setSuccessMessage(res.message || "L'offre a été archivée avec succès (RG17).");
            setOffreToArchive(null);
            chargerOffres(currentPage);
        } catch (err: unknown) {
            const msg = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
                : "Erreur lors de l'archivage de l'offre.";
            setError(msg || "Erreur lors de l'archivage de l'offre.");
        } finally {
            setIsActionLoading(false);
        }
    };

    return (
        <div className="space-y-8 pb-12">
            {/* Entête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <Layers className="w-7 h-7 text-indigo-600" />
                        <span>Modération du Catalogue des Offres</span>
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Surveillance de la qualité des compétences et archivage conforme aux règles RG17.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        leftIcon={<RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />}
                        onClick={() => chargerOffres(currentPage)}
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

            {/* Avertissement RG17 */}
            <div className="bg-indigo-50 border border-indigo-200/90 rounded-2xl p-4 text-xs text-indigo-950 flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                <div className="leading-relaxed">
                    <p className="font-bold">Règle de gestion RG17 — Modération & Archivage</p>
                    <p className="text-indigo-800 mt-0.5">
                        L'archivage d'une offre retire immédiatement celle-ci du catalogue public et annule toutes les demandes en attente. 
                        <strong> Les réservations déjà acceptées demeurent préservées et les crédits restent sécurisés en séquestre jusqu'au déroulement normal de la séance.</strong>
                    </p>
                </div>
            </div>

            {/* Filtres & Recherche */}
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Rechercher par titre, compétence, intervenant..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                        />
                    </div>
                    <div>
                        <select
                            value={statutFilter}
                            onChange={(e) => setStatutFilter(e.target.value)}
                            aria-label="Filtrer par statut"
                            className="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-700"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="publiee">Publiées (actives)</option>
                            <option value="brouillon">Brouillons</option>
                            <option value="archivee">Archivées (RG17)</option>
                        </select>
                    </div>
                    <div>
                        <select
                            value={categoryFilter}
                            onChange={(e) => setCategoryFilter(e.target.value)}
                            aria-label="Filtrer par catégorie"
                            className="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-700"
                        >
                            <option value="">Toutes les catégories</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>{c.nom}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </Card>

            {/* Tableau des Offres */}
            <div className="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50/80 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                <th className="py-3 px-4">Offre & Compétence</th>
                                <th className="py-3 px-4">Intervenant</th>
                                <th className="py-3 px-4">Catégorie</th>
                                <th className="py-3 px-4">Statut</th>
                                <th className="py-3 px-4 text-center">Créneaux</th>
                                <th className="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {isLoading ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-slate-500">
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <Spinner size="md" />
                                            <span>Chargement des offres...</span>
                                        </div>
                                    </td>
                                </tr>
                            ) : offres.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-slate-400">
                                        Aucune offre trouvée avec ces critères.
                                    </td>
                                </tr>
                            ) : (
                                offres.map((o) => {
                                    const intervenantName = o.intervenant
                                        ? `${o.intervenant.prenom} ${o.intervenant.nom}`
                                        : 'Étudiant inconnu';
                                    const creneauxCount = (o as { creneaux_count?: number }).creneaux_count ?? o.creneaux?.length ?? 0;

                                    return (
                                        <tr key={o.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-slate-900 text-sm">{o.titre}</div>
                                                <div className="text-indigo-600 text-[11px] font-semibold mt-0.5">
                                                    Compétence : {o.competence}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center gap-1.5 font-medium text-slate-800">
                                                    <UserIcon className="w-3.5 h-3.5 text-slate-400" />
                                                    <span>{intervenantName}</span>
                                                </div>
                                                <div className="text-[11px] text-slate-400">
                                                    {o.intervenant?.email}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-slate-600 font-medium">
                                                <span className="inline-flex items-center gap-1 bg-slate-100 px-2 py-0.5 rounded-md text-[11px]">
                                                    <Tag className="w-3 h-3 text-slate-400" />
                                                    {o.categorie?.nom || 'Général'}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
                                                {o.statut === 'publiee' && <Badge variant="success">Publiée</Badge>}
                                                {o.statut === 'brouillon' && <Badge variant="warning">Brouillon</Badge>}
                                                {o.statut === 'archivee' && <Badge variant="neutral">Archivée</Badge>}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <span className="inline-flex items-center gap-1 text-slate-700 font-bold bg-slate-100 px-2.5 py-0.5 rounded-full text-xs">
                                                    <Clock className="w-3 h-3 text-slate-400" />
                                                    {creneauxCount}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Voir ${o.titre}`}
                                                        leftIcon={<Eye className="w-3.5 h-3.5" />}
                                                        onClick={() => handleViewOffre(o.id)}
                                                    >
                                                        Détails
                                                    </Button>

                                                    {o.statut !== 'archivee' && (
                                                        <Button
                                                            variant="danger"
                                                            size="sm"
                                                            leftIcon={<Archive className="w-3.5 h-3.5" />}
                                                            onClick={() => setOffreToArchive(o)}
                                                        >
                                                            Archiver
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
                            Page <strong>{currentPage}</strong> sur <strong>{lastPage}</strong> ({total} offres)
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={currentPage <= 1 || isLoading}
                                onClick={() => chargerOffres(currentPage - 1)}
                            >
                                Précédent
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={currentPage >= lastPage || isLoading}
                                onClick={() => chargerOffres(currentPage + 1)}
                            >
                                Suivant
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* Modale Détails Offre */}
            {selectedOffre && (
                <Modal
                    isOpen={true}
                    onClose={() => setSelectedOffre(null)}
                    title={`Modération de l'offre #${selectedOffre.id}`}
                >
                    <div className="space-y-4 text-xs">
                        <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                            <h3 className="font-extrabold text-slate-900 text-base">{selectedOffre.titre}</h3>
                            <p className="text-slate-600 leading-relaxed">{selectedOffre.description}</p>
                            <div className="flex flex-wrap gap-2 pt-2">
                                <Badge variant="neutral">Niveau : {selectedOffre.niveau}</Badge>
                                <Badge variant="neutral">Mode : {selectedOffre.mode}</Badge>
                                <Badge variant="neutral">Statut : {selectedOffre.statut}</Badge>
                            </div>
                        </div>

                        {/* Propriétaire */}
                        <div className="p-4 rounded-2xl bg-indigo-50 border border-indigo-100">
                            <h4 className="font-bold text-indigo-950 mb-2 flex items-center gap-1.5">
                                <UserIcon className="w-4 h-4 text-indigo-600" />
                                Étudiant intervenant
                            </h4>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <span className="text-[11px] text-indigo-700">Nom & Prénom</span>
                                    <p className="font-bold text-slate-900">
                                        {selectedOffre.intervenant?.prenom} {selectedOffre.intervenant?.nom}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-[11px] text-indigo-700">Email</span>
                                    <p className="font-bold text-slate-900">{selectedOffre.intervenant?.email}</p>
                                </div>
                            </div>
                        </div>

                        {/* Créneaux */}
                        <div className="space-y-2">
                            <h4 className="font-bold text-slate-900 flex items-center gap-1.5">
                                <Calendar className="w-4 h-4 text-indigo-600" />
                                Créneaux horaires déclarés ({selectedOffre.creneaux?.length ?? 0})
                            </h4>
                            {(!selectedOffre.creneaux || selectedOffre.creneaux.length === 0) ? (
                                <p className="text-slate-400 italic">Aucun créneau configuré sur cette offre.</p>
                            ) : (
                                <div className="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                    {selectedOffre.creneaux.map((c) => (
                                        <div key={c.id} className="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Clock className="w-3.5 h-3.5 text-slate-400" />
                                                <span className="font-semibold text-slate-800">
                                                    {c.date} ({c.heure_debut} → {c.heure_fin})
                                                </span>
                                            </div>
                                            <Badge variant={c.est_disponible ? "success" : "neutral"}>
                                                {c.est_disponible ? "Libre" : "Réservé"}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end pt-3">
                            <Button variant="secondary" onClick={() => setSelectedOffre(null)}>
                                Fermer
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Modale Confirmation Archivage (RG17) */}
            {offreToArchive && (
                <Modal
                    isOpen={true}
                    onClose={() => setOffreToArchive(null)}
                    title="Confirmer l'archivage de l'offre (RG17)"
                >
                    <div className="space-y-4 text-xs">
                        <AlertBanner
                            variant="danger"
                            title="Archivage administratif du contenu"
                            message={`Vous êtes sur le point d'archiver l'offre "${offreToArchive.titre}".`}
                        />

                        <div className="space-y-2 text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-200 leading-relaxed">
                            <p className="font-bold text-slate-800">Application de la règle RG17 :</p>
                            <ul className="list-disc pl-5 space-y-1">
                                <li>L'offre sera instantanément masquée du catalogue public.</li>
                                <li>Toutes les demandes de réservation en attente d'acceptation seront annulées.</li>
                                <li>Les réservations déjà acceptées par l'intervenant restent préservées.</li>
                                <li>Les crédits des bénéficiaires demeurent en sécurité en séquestre.</li>
                            </ul>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button
                                variant="secondary"
                                onClick={() => setOffreToArchive(null)}
                                disabled={isActionLoading}
                            >
                                Annuler
                            </Button>
                            <Button
                                variant="danger"
                                onClick={handleConfirmArchive}
                                isLoading={isActionLoading}
                            >
                                Confirmer l'archivage
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default AdminOffersPage;
