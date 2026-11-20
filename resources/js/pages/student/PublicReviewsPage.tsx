import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import apiClient from '../../api/client';
import { evaluationsApi } from '../../api/evaluations.api';
import { Evaluation, EvaluationStats } from '../../types/evaluation.types';
import { LaravelPaginatedResponse } from '../../types/api.types';
import { AlertBanner, Card, Pagination } from '../../components/common';
export default function PublicReviewsPage() {
    const { id } = useParams();
    const [stats, setStats] = useState<EvaluationStats | null>(null);
    const [items, setItems] = useState<Evaluation[]>([]);
    const [error, setError] = useState('');
    const [page, setPage] = useState(1);
    const [last, setLast] = useState(1);
    const [total, setTotal] = useState(0);
    useEffect(() => {
        Promise.all([
            evaluationsApi.getStats(Number(id)),
            apiClient.get<LaravelPaginatedResponse<Evaluation>>(`/users/${id}/evaluations`, {
                params: { page },
            }),
        ])
            .then(([s, { data: d }]) => {
                setStats(s);
                setItems(d.data);
                setLast(d.last_page);
                setTotal(d.total);
            })
            .catch(() => setError('Avis indisponibles.'));
    }, [id, page]);
    return (
        <div className="max-w-3xl w-full mx-auto p-6 space-y-4">
            <h1 className="text-2xl font-bold">Avis du profil</h1>
            {error && <AlertBanner variant="error">{error}</AlertBanner>}
            <p>
                {stats?.nombre_evaluations || 0} avis · moyenne {stats?.note_moyenne ?? '—'} / 5
            </p>
            {items.map((e) => (
                <Card key={e.id} title={`${e.auteur?.prenom} ${e.auteur?.nom} · ${e.note} / 5`}>
                    <p className="whitespace-pre-wrap">{e.commentaire || 'Sans commentaire'}</p>
                </Card>
            ))}
            {last > 1 && (
                <Pagination
                    currentPage={page}
                    lastPage={last}
                    total={total}
                    onPageChange={setPage}
                />
            )}
        </div>
    );
}
