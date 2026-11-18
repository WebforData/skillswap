/**
 * SkillSwap Campus — Back-Office : Gestion des Utilisateurs (Module B - RG18)
 */

import React, { useEffect, useState, useCallback } from 'react';
import {
    Users,
    Search,
    Shield,
    UserCheck,
    UserX,
    Eye,
    RefreshCw,
    Coins,
    BookOpen,
    Clock,
    GraduationCap,
    AlertCircle,
} from 'lucide-react';
import { adminApi } from '../../api/admin.api';
import { User } from '../../types/auth.types';
import { useAuth } from '../../hooks/useAuth';
import Button from '../../components/common/Button';
import Badge from '../../components/common/Badge';
import AlertBanner from '../../components/common/AlertBanner';
import Modal from '../../components/common/Modal';
import Spinner from '../../components/common/Spinner';
import Card from '../../components/common/Card';

export const AdminUsersPage: React.FC = () => {
    const { user: currentAdmin } = useAuth();
    const [users, setUsers] = useState<User[]>([]);
    const [total, setTotal] = useState(0);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    // Filtres
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('');
    const [statutFilter, setStatutFilter] = useState('');

    // Modale Détail Utilisateur
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // Modale Confirmation Désactivation (RG18)
    const [userToDeactivate, setUserToDeactivate] = useState<User | null>(null);
    const [isActionLoading, setIsActionLoading] = useState(false);

    const chargerUtilisateurs = useCallback(async (page = 1) => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await adminApi.getUsers({
                page,
                q: search.trim() || undefined,
                role: roleFilter || undefined,
                statut: statutFilter || undefined,
            });
            setUsers(data.users || []);
            setTotal(data.total);
            setCurrentPage(data.current_page);
            setLastPage(data.last_page);
        } catch {
            setError("Erreur lors de la récupération de la liste des utilisateurs.");
        } finally {
            setIsLoading(false);
        }
    }, [search, roleFilter, statutFilter]);

    useEffect(() => {
        const timer = setTimeout(() => {
            chargerUtilisateurs(1);
        }, 300);
        return () => clearTimeout(timer);
    }, [chargerUtilisateurs]);

    // Voir les détails d'un utilisateur
    const handleViewUser = async (id: number) => {
        try {
            const data = await adminApi.getUser(id);
            setSelectedUser(data.user);
        } catch {
            setError("Impossible de charger les détails complets de cet utilisateur.");
        }
    };

    // Confirmer la désactivation (RG18)
    const handleConfirmDeactivate = async () => {
        if (!userToDeactivate) return;

        // Protection : interdire à l'admin de se désactiver lui-même
        if (currentAdmin && Number(currentAdmin.id) === Number(userToDeactivate.id)) {
            setError("Sécurité : vous ne pouvez pas désactiver votre propre compte administrateur.");
            setUserToDeactivate(null);
            return;
        }

        setIsActionLoading(true);
        setError(null);
        try {
            const res = await adminApi.desactiverUser(userToDeactivate.id);
            setSuccessMessage(res.message);
            setUserToDeactivate(null);
            chargerUtilisateurs(currentPage);
        } catch (err: unknown) {
            const errorMsg = err && typeof err === 'object' && 'response' in err
                ? ((err as { response?: { data?: { message?: string } } }).response?.data?.message)
                : "Erreur lors de la désactivation du compte.";
            setError(errorMsg || "Erreur lors de la désactivation du compte.");
        } finally {
            setIsActionLoading(false);
        }
    };

    // Réactiver un compte
    const handleActivate = async (user: User) => {
        setIsActionLoading(true);
        setError(null);
        try {
            const res = await adminApi.activerUser(user.id);
            setSuccessMessage(res.message);
            chargerUtilisateurs(currentPage);
        } catch {
            setError("Erreur lors de la réactivation du compte.");
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
                        <Users className="w-7 h-7 text-indigo-600" />
                        <span>Gestion des Étudiants & Utilisateurs</span>
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Administration des comptes, modération des statuts et protection des soldes (RG18).
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        leftIcon={<RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />}
                        onClick={() => chargerUtilisateurs(currentPage)}
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

            {/* Notice d'information RG18 */}
            <div className="bg-amber-50 border border-amber-200/90 rounded-2xl p-4 text-xs text-amber-900 flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div className="leading-relaxed">
                    <p className="font-bold">Règle de gestion RG18 — Désactivation de compte</p>
                    <p className="text-amber-800 mt-0.5">
                        La désactivation d'un compte étudiant révoque immédiatement ses accès de connexion et annule automatiquement ses demandes de réservation en attente.
                        <strong> Les réservations déjà acceptées demeurent préservées et les crédits bloqués en séquestre ne sont jamais confisqués.</strong>
                    </p>
                </div>
            </div>

            {/* Barre de Filtres & Recherche */}
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Rechercher par nom, email ou filière..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                        />
                    </div>
                    <div>
                        <select
                            value={roleFilter}
                            onChange={(e) => setRoleFilter(e.target.value)}
                            aria-label="Filtrer par rôle"
                            className="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-700"
                        >
                            <option value="">Tous les rôles</option>
                            <option value="etudiant">Étudiants</option>
                            <option value="administrateur">Administrateurs</option>
                        </select>
                    </div>
                    <div>
                        <select
                            value={statutFilter}
                            onChange={(e) => setStatutFilter(e.target.value)}
                            aria-label="Filtrer par statut"
                            className="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-700"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="actif">Actifs</option>
                            <option value="desactive">Désactivés</option>
                        </select>
                    </div>
                </div>
            </Card>

            {/* Tableau des Utilisateurs */}
            <div className="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50/80 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                <th className="py-3 px-4">Utilisateur</th>
                                <th className="py-3 px-4">Rôle</th>
                                <th className="py-3 px-4">Statut</th>
                                <th className="py-3 px-4">Filière</th>
                                <th className="py-3 px-4 text-center">Portefeuille</th>
                                <th className="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {isLoading ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-slate-500">
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <Spinner size="md" />
                                            <span>Chargement de la liste des utilisateurs...</span>
                                        </div>
                                    </td>
                                </tr>
                            ) : users.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-slate-400">
                                        Aucun utilisateur ne correspond aux critères de recherche.
                                    </td>
                                </tr>
                            ) : (
                                users.map((u) => {
                                    const isSelf = Boolean(currentAdmin && Number(currentAdmin.id) === Number(u.id));
                                    const soldeDispo = u.portefeuille?.solde_disponible ?? (u as { solde_disponible?: number }).solde_disponible ?? 0;
                                    const creditsBloques = u.portefeuille?.credits_bloques ?? (u as { credits_bloques?: number }).credits_bloques ?? 0;

                                    return (
                                        <tr key={u.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-slate-900 text-sm">
                                                    {u.prenom} {u.nom}
                                                    {isSelf && (
                                                        <span className="ml-2 text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                                            Vous
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-slate-500 text-[11px]">{u.email}</div>
                                            </td>
                                            <td className="py-3 px-4">
                                                {u.role === 'administrateur' ? (
                                                    <span className="inline-flex items-center gap-1 font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md text-[11px]">
                                                        <Shield className="w-3 h-3" /> Admin
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md text-[11px]">
                                                        <GraduationCap className="w-3 h-3" /> Étudiant
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {u.statut === 'actif' ? (
                                                    <Badge variant="success">Actif</Badge>
                                                ) : (
                                                    <Badge variant="danger">Désactivé</Badge>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-slate-600">
                                                {u.filiere || 'Non renseignée'}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <div className="inline-flex items-center gap-1.5 font-bold text-slate-800 bg-slate-100 px-2.5 py-1 rounded-lg">
                                                    <Coins className="w-3.5 h-3.5 text-amber-500" />
                                                    <span>{soldeDispo} dispo</span>
                                                    {creditsBloques > 0 && (
                                                        <span className="text-[10px] text-amber-700 font-semibold">
                                                            ({creditsBloques} séquestré)
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Voir ${u.prenom} ${u.nom}`}
                                                        leftIcon={<Eye className="w-3.5 h-3.5" />}
                                                        onClick={() => handleViewUser(u.id)}
                                                    >
                                                        Détails
                                                    </Button>

                                                    {u.statut === 'actif' ? (
                                                        <Button
                                                            variant="danger"
                                                            size="sm"
                                                            leftIcon={<UserX className="w-3.5 h-3.5" />}
                                                            onClick={() => setUserToDeactivate(u)}
                                                            disabled={isSelf}
                                                            title={isSelf ? "Vous ne pouvez pas désactiver votre propre compte" : "Désactiver le compte (RG18)"}
                                                        >
                                                            Désactiver
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            leftIcon={<UserCheck className="w-3.5 h-3.5 text-emerald-600" />}
                                                            onClick={() => handleActivate(u)}
                                                            disabled={isActionLoading}
                                                        >
                                                            Réactiver
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
                            Page <strong>{currentPage}</strong> sur <strong>{lastPage}</strong> ({total} utilisateurs)
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={currentPage <= 1 || isLoading}
                                onClick={() => chargerUtilisateurs(currentPage - 1)}
                            >
                                Précédent
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={currentPage >= lastPage || isLoading}
                                onClick={() => chargerUtilisateurs(currentPage + 1)}
                            >
                                Suivant
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* Modale Détails Utilisateur */}
            {selectedUser && (
                <Modal
                    isOpen={true}
                    onClose={() => setSelectedUser(null)}
                    title={`Fiche Utilisateur : ${selectedUser.prenom} ${selectedUser.nom}`}
                >
                    <div className="space-y-5 text-xs">
                        <div className="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100">
                            <div>
                                <span className="text-slate-400 font-medium">Email universitaire</span>
                                <p className="font-bold text-slate-800 text-sm mt-0.5">{selectedUser.email}</p>
                            </div>
                            <div>
                                <span className="text-slate-400 font-medium">Filière / Campus</span>
                                <p className="font-bold text-slate-800 text-sm mt-0.5">{selectedUser.filiere || 'N/A'}</p>
                            </div>
                            <div>
                                <span className="text-slate-400 font-medium">Rôle</span>
                                <p className="font-bold text-slate-800 mt-0.5 capitalize">{selectedUser.role}</p>
                            </div>
                            <div>
                                <span className="text-slate-400 font-medium">Statut du compte</span>
                                <div className="mt-0.5">
                                    {selectedUser.statut === 'actif' ? (
                                        <Badge variant="success">Actif</Badge>
                                    ) : (
                                        <Badge variant="danger">Désactivé (RG18)</Badge>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Soldes */}
                        <div className="p-4 rounded-2xl bg-indigo-50 border border-indigo-100 space-y-2">
                            <h4 className="font-bold text-indigo-950 flex items-center gap-1.5">
                                <Coins className="w-4 h-4 text-indigo-600" />
                                Portefeuille en temps réel (RG16)
                            </h4>
                            <div className="grid grid-cols-3 gap-3 pt-2">
                                <div>
                                    <span className="text-[11px] text-indigo-700">Disponible</span>
                                    <p className="font-black text-indigo-900 text-base">
                                        {selectedUser.portefeuille?.solde_disponible ?? (selectedUser as { solde_disponible?: number }).solde_disponible ?? 0} crédits
                                    </p>
                                </div>
                                <div>
                                    <span className="text-[11px] text-indigo-700">Séquestré</span>
                                    <p className="font-black text-amber-700 text-base">
                                        {selectedUser.portefeuille?.credits_bloques ?? (selectedUser as { credits_bloques?: number }).credits_bloques ?? 0} crédits
                                    </p>
                                </div>
                                <div>
                                    <span className="text-[11px] text-indigo-700">Total</span>
                                    <p className="font-black text-slate-900 text-base">
                                        {(selectedUser.portefeuille?.solde_disponible ?? 0) + (selectedUser.portefeuille?.credits_bloques ?? 0)} crédits
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Statistiques d'activité si disponibles */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                                <span className="flex items-center gap-1.5 text-slate-600">
                                    <BookOpen className="w-4 h-4 text-indigo-500" /> Offres créées
                                </span>
                                <span className="font-bold text-slate-900">
                                    {(selectedUser as { offres_count?: number }).offres_count ?? 0}
                                </span>
                            </div>
                            <div className="p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                                <span className="flex items-center gap-1.5 text-slate-600">
                                    <Clock className="w-4 h-4 text-indigo-500" /> Demandes reçues
                                </span>
                                <span className="font-bold text-slate-900">
                                    {(selectedUser as { reservations_demandees_count?: number }).reservations_demandees_count ?? 0}
                                </span>
                            </div>
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button variant="secondary" onClick={() => setSelectedUser(null)}>
                                Fermer
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Modale Confirmation Désactivation (RG18) */}
            {userToDeactivate && (
                <Modal
                    isOpen={true}
                    onClose={() => setUserToDeactivate(null)}
                    title="Confirmer la désactivation du compte"
                >
                    <div className="space-y-4 text-xs">
                        <AlertBanner
                            variant="danger"
                            title="Action impactante sur le compte"
                            message={`Vous êtes sur le point de désactiver le compte de ${userToDeactivate.prenom} ${userToDeactivate.nom} (${userToDeactivate.email}).`}
                        />

                        <div className="space-y-2 text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-200 leading-relaxed">
                            <p className="font-bold text-slate-800">Conséquences de la désactivation (RG18) :</p>
                            <ul className="list-disc pl-5 space-y-1">
                                <li>L'utilisateur ne pourra plus se connecter à la plateforme.</li>
                                <li>Toutes ses demandes de réservation en attente seront automatiquement annulées.</li>
                                <li>Les réservations déjà acceptées demeurent préservées pour l'autre participant.</li>
                                <li>Les crédits bloqués en séquestre ne sont jamais confisqués.</li>
                            </ul>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button
                                variant="secondary"
                                onClick={() => setUserToDeactivate(null)}
                                disabled={isActionLoading}
                            >
                                Annuler
                            </Button>
                            <Button
                                variant="danger"
                                onClick={handleConfirmDeactivate}
                                isLoading={isActionLoading}
                            >
                                Confirmer la désactivation
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default AdminUsersPage;
