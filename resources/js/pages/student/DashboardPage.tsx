import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { studentApi } from '../../api/student.api';
import { Offre } from '../../types/offre.types';
import { AlertBanner, Card, EmptyState, Spinner } from '../../components/common';
export default function DashboardPage() {
    const { user } = useAuth();
    const [offers, setOffers] = useState<Offre[]>([]);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    useEffect(() => {
        studentApi
            .suggestions()
            .then(setOffers)
            .catch(() => setError('Impossible de charger les suggestions.'))
            .finally(() => setLoading(false));
    }, []);
    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold">Bonjour, {user?.prenom}</h1>
            <div className="grid sm:grid-cols-3 gap-4">
                <Card title="Crédits disponibles">
                    {user?.portefeuille?.solde_disponible ?? 0} h
                </Card>
                <Card title="Crédits bloqués">{user?.portefeuille?.credits_bloques ?? 0} h</Card>
                <Card title="Votre espace">
                    <Link className="text-indigo-700 underline" to="/app/planning">
                        Consulter mon planning
                    </Link>
                </Card>
            </div>
            <Card title="Suggestions pour vous">
                <p className="text-sm text-slate-600 mb-4">
                    Selon vos compétences recherchées :{' '}
                    {user?.competences_recherchees || 'à renseigner dans votre profil'}.
                </p>
                {error && <AlertBanner variant="error">{error}</AlertBanner>}
                {loading ? (
                    <Spinner />
                ) : offers.length ? (
                    <div className="grid md:grid-cols-2 gap-3">
                        {offers.map((o) => (
                            <Link
                                className="rounded-xl border p-4 hover:bg-indigo-50"
                                to={`/app/offres/${o.id}`}
                                key={o.id}
                            >
                                <h3 className="font-semibold">{o.titre}</h3>
                                <p className="text-sm">
                                    {o.competence} · {o.categorie?.nom} · {o.duree} h
                                </p>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        title="Aucune suggestion disponible"
                        description="Renseignez vos compétences recherchées, ou explorez le catalogue."
                    />
                )}
            </Card>
            <div className="flex flex-wrap gap-4 text-indigo-700 underline">
                <Link to="/app/catalogue">Catalogue</Link>
                <Link to="/app/notifications">Mes notifications</Link>
                <Link to="/app/profil">Modifier mon profil et voir mon historique</Link>
            </div>
        </div>
    );
}
