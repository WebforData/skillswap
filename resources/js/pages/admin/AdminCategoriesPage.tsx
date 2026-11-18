/**
 * SkillSwap Campus — Back-Office : Gestion des Catégories (Module C)
 */

import React, { useEffect, useState, useCallback } from 'react';
import {
    Tag,
    Plus,
    Edit2,
    Trash2,
    RefreshCw,
    Search,
    Layers,
    AlertCircle,
} from 'lucide-react';
import { adminApi } from '../../api/admin.api';
import { Categorie } from '../../types/offre.types';
import Button from '../../components/common/Button';
import AlertBanner from '../../components/common/AlertBanner';
import Modal from '../../components/common/Modal';
import Spinner from '../../components/common/Spinner';
import Card from '../../components/common/Card';

export const AdminCategoriesPage: React.FC = () => {
    const [categories, setCategories] = useState<Categorie[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    // Recherche
    const [searchTerm, setSearchTerm] = useState('');

    // Modale Création / Édition
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Categorie | null>(null);
    const [categoryName, setCategoryName] = useState('');
    const [formError, setFormError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    // Modale Suppression
    const [categoryToDelete, setCategoryToDelete] = useState<Categorie | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const chargerCategories = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await adminApi.getCategories();
            setCategories(data.categories || []);
        } catch {
            setError("Impossible de charger les catégories de compétences.");
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        chargerCategories();
    }, [chargerCategories]);

    // Ouvrir modale création
    const handleOpenCreateModal = () => {
        setEditingCategory(null);
        setCategoryName('');
        setFormError(null);
        setIsEditModalOpen(true);
    };

    // Ouvrir modale édition
    const handleOpenEditModal = (cat: Categorie) => {
        setEditingCategory(cat);
        setCategoryName(cat.nom);
        setFormError(null);
        setIsEditModalOpen(true);
    };

    // Soumission formulaire
    const handleSaveCategory = async (e: React.FormEvent) => {
        e.preventDefault();
        const trimmed = categoryName.trim();
        if (trimmed.length < 2) {
            setFormError("Le nom de la catégorie doit comporter au moins 2 caractères.");
            return;
        }
        if (trimmed.length > 100) {
            setFormError("Le nom de la catégorie ne peut pas dépasser 100 caractères.");
            return;
        }

        setIsSaving(true);
        setFormError(null);
        setError(null);

        try {
            if (editingCategory) {
                const res = await adminApi.modifierCategorie(editingCategory.id, trimmed);
                setSuccessMessage(res.message || "Catégorie mise à jour avec succès.");
            } else {
                const res = await adminApi.creerCategorie(trimmed);
                setSuccessMessage(res.message || "Catégorie créée avec succès.");
            }
            setIsEditModalOpen(false);
            chargerCategories();
        } catch (err: unknown) {
            const errResp = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data
                : null;

            if (errResp?.errors?.nom?.[0]) {
                setFormError(errResp.errors.nom[0]);
            } else if (errResp?.message) {
                setFormError(errResp.message);
            } else {
                setFormError("Erreur lors de l'enregistrement de la catégorie.");
            }
        } finally {
            setIsSaving(false);
        }
    };

    // Confirmer suppression
    const handleConfirmDelete = async () => {
        if (!categoryToDelete) return;

        setIsDeleting(true);
        setError(null);
        try {
            const res = await adminApi.supprimerCategorie(categoryToDelete.id);
            setSuccessMessage(res.message || "Catégorie supprimée avec succès.");
            setCategoryToDelete(null);
            chargerCategories();
        } catch (err: unknown) {
            const errResp = err && typeof err === 'object' && 'response' in err
                ? (err as { response?: { status?: number; data?: { message?: string } } }).response
                : null;

            if (errResp?.status === 409) {
                setError(errResp.data?.message || "Suppression impossible : des offres de compétences sont rattachées à cette catégorie.");
            } else {
                setError(errResp?.data?.message || "Erreur lors de la suppression de la catégorie.");
            }
            setCategoryToDelete(null);
        } finally {
            setIsDeleting(false);
        }
    };

    const filteredCategories = categories.filter((c) =>
        c.nom.toLowerCase().includes(searchTerm.toLowerCase().trim())
    );

    return (
        <div className="space-y-8 pb-12">
            {/* Entête */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <Tag className="w-7 h-7 text-indigo-600" />
                        <span>Gestion des Catégories de Compétences</span>
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Classification thématique du catalogue universitaire et règles d'intégrité référentielle.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        leftIcon={<RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />}
                        onClick={chargerCategories}
                        disabled={isLoading}
                    >
                        Actualiser
                    </Button>
                    <Button
                        variant="primary"
                        size="sm"
                        leftIcon={<Plus className="w-4 h-4" />}
                        onClick={handleOpenCreateModal}
                    >
                        Nouvelle Catégorie
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

            {/* Avertissement intégrité */}
            <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs text-slate-700 flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                <div className="leading-relaxed">
                    <p className="font-bold text-slate-900">Règle de protection d'intégrité des compétences</p>
                    <p className="text-slate-600 mt-0.5">
                        Une catégorie ne peut être supprimée que si aucune offre étudiante (active, brouillon ou archivée) ne lui est rattachée. Le backend garantit cette contrainte et rejettera toute tentative non conforme avec un statut HTTP 409.
                    </p>
                </div>
            </div>

            {/* Recherche */}
            <Card>
                <div className="relative">
                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        type="text"
                        placeholder="Filtrer les catégories par nom..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                    />
                </div>
            </Card>

            {/* Liste des catégories */}
            <div className="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50/80 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                <th className="py-3 px-4">Nom de la Catégorie</th>
                                <th className="py-3 px-4 text-center">Offres Rattachées</th>
                                <th className="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {isLoading ? (
                                <tr>
                                    <td colSpan={3} className="py-12 text-center text-slate-500">
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <Spinner size="md" />
                                            <span>Chargement des catégories...</span>
                                        </div>
                                    </td>
                                </tr>
                            ) : filteredCategories.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="py-12 text-center text-slate-400">
                                        Aucune catégorie trouvée.
                                    </td>
                                </tr>
                            ) : (
                                filteredCategories.map((cat) => {
                                    const offresCount = cat.offres_count ?? 0;

                                    return (
                                        <tr key={cat.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4 font-bold text-slate-900 text-sm">
                                                <div className="flex items-center gap-2">
                                                    <Tag className="w-4 h-4 text-indigo-500 shrink-0" />
                                                    <span>{cat.nom}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-bold text-xs ${
                                                    offresCount > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-500'
                                                }`}>
                                                    <Layers className="w-3 h-3" />
                                                    {offresCount} offre{offresCount > 1 ? 's' : ''}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        leftIcon={<Edit2 className="w-3.5 h-3.5" />}
                                                        onClick={() => handleOpenEditModal(cat)}
                                                    >
                                                        Modifier
                                                    </Button>
                                                    <Button
                                                        variant="danger"
                                                        size="sm"
                                                        leftIcon={<Trash2 className="w-3.5 h-3.5" />}
                                                        onClick={() => setCategoryToDelete(cat)}
                                                    >
                                                        Supprimer
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modale Création / Édition */}
            {isEditModalOpen && (
                <Modal
                    isOpen={true}
                    onClose={() => !isSaving && setIsEditModalOpen(false)}
                    title={editingCategory ? "Modifier la catégorie" : "Créer une nouvelle catégorie"}
                >
                    <form onSubmit={handleSaveCategory} className="space-y-4 text-xs">
                        {formError && (
                            <AlertBanner variant="danger" message={formError} />
                        )}

                        <div>
                            <label className="block text-slate-700 font-bold mb-1">
                                Nom de la catégorie <span className="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={categoryName}
                                onChange={(e) => setCategoryName(e.target.value)}
                                placeholder="Ex : Informatique, Langues, Mathématiques..."
                                required
                                minLength={2}
                                maxLength={100}
                                disabled={isSaving}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                            />
                            <p className="text-[11px] text-slate-400 mt-1">
                                Doit être clair et unique. Entre 2 et 100 caractères.
                            </p>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setIsEditModalOpen(false)}
                                disabled={isSaving}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                isLoading={isSaving}
                            >
                                {editingCategory ? "Enregistrer les modifications" : "Créer la catégorie"}
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Modale Confirmation Suppression */}
            {categoryToDelete && (
                <Modal
                    isOpen={true}
                    onClose={() => !isDeleting && setCategoryToDelete(null)}
                    title="Confirmer la suppression"
                >
                    <div className="space-y-4 text-xs">
                        <AlertBanner
                            variant="danger"
                            title="Action irréversible"
                            message={`Êtes-vous sûr de vouloir supprimer la catégorie "${categoryToDelete.nom}" ?`}
                        />

                        {(categoryToDelete.offres_count ?? 0) > 0 && (
                            <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 leading-relaxed">
                                <p className="font-bold">Attention : cette catégorie contient {categoryToDelete.offres_count} offre(s).</p>
                                <p className="mt-0.5">
                                    Le serveur rejettera cette suppression avec un statut 409 pour protéger l'intégrité du catalogue.
                                </p>
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button
                                variant="secondary"
                                onClick={() => setCategoryToDelete(null)}
                                disabled={isDeleting}
                            >
                                Annuler
                            </Button>
                            <Button
                                variant="danger"
                                onClick={handleConfirmDelete}
                                isLoading={isDeleting}
                            >
                                Supprimer définitivement
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default AdminCategoriesPage;
