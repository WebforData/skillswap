/**
 * SkillSwap Campus — Catalogue Public des Offres d'Échange (RG02, RG04)
 */

import React, { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
    Search,
    X,
    Clock,
    BookOpen,
    MapPin,
    Laptop,
    ArrowRight,
    Sparkles,
    Coins,
} from 'lucide-react';
import { offresApi } from '../../api/offres.api';
import { Offre, OffreFiltres, NiveauOffre, ModeOffre } from '../../types/offre.types';
import { LaravelPaginatedResponse } from '../../types/api.types';
import { Button, Input, Select, Badge, Card, Spinner, Pagination, EmptyState, AlertBanner } from '../../components/common';

export const CataloguePage: React.FC = () => {
    const [searchParams, setSearchParams] = useSearchParams();

    // Filtres tirés de l'URL ou des valeurs par défaut
    const [searchTerm, setSearchTerm] = useState(searchParams.get('q') || '');
    const [niveau, setNiveau] = useState<NiveauOffre | ''>((searchParams.get('niveau') as any) || '');
    const [mode, setMode] = useState<ModeOffre | ''>((searchParams.get('mode') as any) || '');
    const [duree, setDuree] = useState<string>(searchParams.get('duree') || '');
    const [currentPage, setCurrentPage] = useState<number>(Number(searchParams.get('page')) || 1);

    // Données et état réseau
    const [offresData, setOffresData] = useState<LaravelPaginatedResponse<Offre> | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Chargement des offres
    const chargerOffres = useCallback(async (page: number) => {
        setIsLoading(true);
        setError(null);

        const filtres: OffreFiltres = {
            page,
            per_page: 9,
        };

        if (searchTerm.trim()) filtres.q = searchTerm.trim();
        if (niveau) filtres.niveau = niveau;
        if (mode) filtres.mode = mode;
        if (duree) filtres.duree = Number(duree);

        try {
            const data = await offresApi.getCatalogue(filtres);
            setOffresData(data);
        } catch (err: any) {
            setError(
                err?.response?.data?.message ||
                "Impossible de charger le catalogue d'offres. Veuillez réessayer."
            );
        } finally {
            setIsLoading(false);
        }
    }, [searchTerm, niveau, mode, duree]);

    // Déclencher le chargement lorsque la page ou les filtres changent
    useEffect(() => {
        chargerOffres(currentPage);
    }, [currentPage, chargerOffres]);

    // Mise à jour des filtres et synchronisation avec les paramètres d'URL
    const appliquerFiltres = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        setCurrentPage(1);

        const params: Record<string, string> = {};
        if (searchTerm.trim()) params.q = searchTerm.trim();
        if (niveau) params.niveau = niveau;
        if (mode) params.mode = mode;
        if (duree) params.duree = duree;
        params.page = '1';

        setSearchParams(params);
        chargerOffres(1);
    };

    const reinitialiserFiltres = () => {
        setSearchTerm('');
        setNiveau('');
        setMode('');
        setDuree('');
        setCurrentPage(1);
        setSearchParams({});
    };

    const filtresActifs = Boolean(searchTerm.trim() || niveau || mode || duree);

    return (
        <div className="space-y-8">
            {/* En-tête de page */}
            <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold mb-2">
                        <Sparkles className="w-3.5 h-3.5" />
                        <span>Catalogue Universitaire</span>
                    </div>
                    <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Explorer les cours et compétences
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 max-w-2xl">
                        Consultez les offres proposées par vos camarades étudiants.
                        Chaque séance est équitablement valorisée selon sa durée (1h = 1 crédit, 2h = 2 crédits).
                    </p>
                </div>

                <div className="shrink-0 flex items-center gap-2 text-xs font-medium text-slate-600 bg-slate-100 px-3 py-2 rounded-xl">
                    <Coins className="w-4 h-4 text-amber-500" />
                    <span>Taux d'échange garanti : 1 heure = 1 crédit</span>
                </div>
            </div>

            {/* Barre de Recherche et Filtres */}
            <div className="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-xs space-y-4">
                <form onSubmit={appliquerFiltres} className="flex flex-col sm:flex-row gap-3">
                    <div className="flex-1">
                        <Input
                            id="search-input"
                            label="Recherche par mot-clé"
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Rechercher une compétence, matière, mot-clé..."
                            icon={<Search className="w-4 h-4 text-slate-400" />}
                        />
                    </div>
                    <div className="flex items-end gap-2">
                        <Button
                            type="submit"
                            variant="primary"
                            icon={<Search className="w-4 h-4" />}
                        >
                            Rechercher
                        </Button>
                        {filtresActifs && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={reinitialiserFiltres}
                                icon={<X className="w-4 h-4" />}
                            >
                                Réinitialiser
                            </Button>
                        )}
                    </div>
                </form>

                {/* Filtres secondaires */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                    <Select
                        id="filter-niveau"
                        label="Niveau de difficulté"
                        value={niveau}
                        onChange={(e) => {
                            setNiveau(e.target.value as any);
                            setCurrentPage(1);
                        }}
                        options={[
                            { value: '', label: 'Tous les niveaux' },
                            { value: 'debutant', label: 'Débutant' },
                            { value: 'intermediaire', label: 'Intermédiaire' },
                            { value: 'avance', label: 'Avancé' },
                        ]}
                    />

                    <Select
                        id="filter-mode"
                        label="Modalité de cours"
                        value={mode}
                        onChange={(e) => {
                            setMode(e.target.value as any);
                            setCurrentPage(1);
                        }}
                        options={[
                            { value: '', label: 'Toutes les modalités' },
                            { value: 'presentiel', label: 'Présentiel (Campus)' },
                            { value: 'distanciel', label: 'Distanciel (Visio)' },
                        ]}
                    />

                    <Select
                        id="filter-duree"
                        label="Durée de la séance (RG02)"
                        value={duree}
                        onChange={(e) => {
                            setDuree(e.target.value);
                            setCurrentPage(1);
                        }}
                        options={[
                            { value: '', label: 'Toutes les durées' },
                            { value: '1', label: '1 heure (1 crédit)' },
                            { value: '2', label: '2 heures (2 crédits)' },
                        ]}
                    />
                </div>
            </div>

            {/* Message d'erreur éventuel */}
            {error && (
                <AlertBanner
                    variant="error"
                    message={error}
                    onClose={() => setError(null)}
                />
            )}

            {/* État de chargement */}
            {isLoading && (
                <div className="py-20 flex flex-col items-center justify-center">
                    <Spinner size="lg" />
                    <p className="mt-3 text-sm text-slate-500">Chargement des offres en cours...</p>
                </div>
            )}

            {/* Liste des offres */}
            {!isLoading && offresData && (
                <>
                    {/* Indicateur de résultats */}
                    <div className="flex items-center justify-between text-xs text-slate-500">
                        <span>
                            <strong className="text-slate-800 font-semibold">{offresData.total}</strong> offre(s)
                            disponible(s)
                        </span>
                        {filtresActifs && (
                            <span className="text-indigo-600 font-medium">
                                Filtres actifs appliqués
                            </span>
                        )}
                    </div>

                    {offresData.data.length === 0 ? (
                        <EmptyState
                            icon={<BookOpen className="w-10 h-10" />}
                            title="Aucune offre trouvée"
                            description={
                                filtresActifs
                                    ? "Aucune offre ne correspond à vos critères de recherche. Essayez d'ajuster ou de réinitialiser vos filtres."
                                    : "Aucune offre n'est actuellement publiée sur la plateforme."
                            }
                            action={
                                filtresActifs ? (
                                    <Button variant="outline" onClick={reinitialiserFiltres}>
                                        Effacer les filtres
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {offresData.data.map((offre) => (
                                <Card key={offre.id} className="flex flex-col h-full hover:shadow-md transition-shadow">
                                    <div className="flex items-center justify-between gap-2 mb-2">
                                        {offre.categorie ? (
                                            <span className="text-[11px] font-semibold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
                                                {offre.categorie.nom}
                                            </span>
                                        ) : (
                                            <span className="text-[11px] font-semibold text-slate-400">
                                                Général
                                            </span>
                                        )}
                                        <div className="flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60">
                                            <Coins className="w-3.5 h-3.5" />
                                            <span>{offre.duree} crédit{offre.duree > 1 ? 's' : ''}</span>
                                        </div>
                                    </div>

                                    <h3 className="text-base font-bold text-slate-900 line-clamp-1 mb-1">
                                        {offre.titre}
                                    </h3>

                                    <p className="text-xs font-medium text-indigo-700 bg-indigo-50/50 p-1.5 rounded-lg mb-3">
                                        Compétence : <span className="font-semibold">{offre.competence}</span>
                                    </p>

                                    <p className="text-xs text-slate-600 line-clamp-3 mb-4 flex-1">
                                        {offre.description}
                                    </p>

                                    {/* Badges de détails */}
                                    <div className="flex flex-wrap items-center gap-1.5 mb-4 pt-3 border-t border-slate-100">
                                        <Badge status={offre.niveau} />
                                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                                            {offre.mode === 'presentiel' ? (
                                                <>
                                                    <MapPin className="w-3 h-3 text-emerald-600" />
                                                    <span>Présentiel</span>
                                                </>
                                            ) : (
                                                <>
                                                    <Laptop className="w-3 h-3 text-sky-600" />
                                                    <span>Distanciel</span>
                                                </>
                                            )}
                                        </span>
                                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                                            <Clock className="w-3 h-3 text-slate-500" />
                                            <span>{offre.duree}h</span>
                                        </span>
                                    </div>

                                    {/* Intervenant et CTA */}
                                    <div className="flex items-center justify-between pt-3 border-t border-slate-100 mt-auto">
                                        <div className="flex items-center gap-2 min-w-0">
                                            <div className="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                {offre.intervenant?.prenom?.charAt(0) || 'E'}
                                            </div>
                                            <div className="min-w-0 text-xs">
                                                <div className="font-semibold text-slate-800 truncate">
                                                    {offre.intervenant?.prenom} {offre.intervenant?.nom}
                                                </div>
                                                {offre.intervenant?.filiere && (
                                                    <div className="text-[10px] text-slate-400 truncate">
                                                        {offre.intervenant.filiere}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <Link to={`/offres/${offre.id}`}>
                                            <Button variant="secondary" size="sm" rightIcon={<ArrowRight className="w-3.5 h-3.5" />}>
                                                Voir l'offre
                                            </Button>
                                        </Link>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}

                    {/* Pagination Laravel */}
                    {offresData.last_page > 1 && (
                        <div className="pt-4 flex justify-center">
                            <Pagination
                                currentPage={offresData.current_page}
                                lastPage={offresData.last_page}
                                onPageChange={(page) => {
                                    setCurrentPage(page);
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                }}
                            />
                        </div>
                    )}
                </>
            )}
        </div>
    );
};
export default CataloguePage;
