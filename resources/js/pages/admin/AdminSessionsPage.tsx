import { useEffect, useState } from 'react';
import apiClient from '../../api/client';
import { Reservation } from '../../types/reservation.types';
import { LaravelPaginatedResponse } from '../../types/api.types';
import { AlertBanner, Button, Card, Select, Pagination } from '../../components/common';
export default function AdminSessionsPage() {
    const [items, setItems] = useState<Reservation[]>([]);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [saving, setSaving] = useState(false);
    const [decisions, setDecisions] = useState<Record<number, string>>({});
    const [reasons, setReasons] = useState<Record<number, string>>({});
    const [page, setPage] = useState(1);
    const [last, setLast] = useState(1);
    const [total, setTotal] = useState(0);
    async function load() {
        try {
            const { data: d } = await apiClient.get<LaravelPaginatedResponse<Reservation>>(
                '/admin/reservations',
                { params: { page } },
            );
            setItems(d.data);
            setLast(d.last_page);
            setTotal(d.total);
        } catch {
            setError('Impossible de charger les séances.');
        }
    }
    useEffect(() => {
        void load();
    }, [page]);
    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-bold">Séances de comptes désactivés</h1>
            <p className="text-sm text-slate-600">
                Les crédits restent bloqués jusqu’au règlement administratif. Un transfert nécessite
                une séance terminée.
            </p>
            {error && <AlertBanner variant="error">{error}</AlertBanner>}
            {success && <AlertBanner variant="success">{success}</AlertBanner>}
            {items.map((r) => (
                <Card key={r.id} title={`Séance #${r.id} — ${r.offre?.titre}`}>
                    <p>
                        {r.offre?.intervenant?.prenom} → {r.beneficiaire?.prenom} ·{' '}
                        {r.creneau?.date} · {r.cout_credits} crédit(s)
                    </p>
                    <form
                        className="space-y-3 mt-4"
                        onSubmit={async (e) => {
                            e.preventDefault();
                            if (!confirm('Ce règlement est définitif. Continuer ?')) return;
                            setSaving(true);
                            setError('');
                            setSuccess('');
                            try {
                                await apiClient.post(`/admin/reservations/${r.id}/settle`, {
                                    decision: decisions[r.id] || 'restitution_beneficiaire',
                                    motif: reasons[r.id] || '',
                                });
                                setSuccess('Séance réglée définitivement.');
                                await load();
                            } catch (e: any) {
                                setError(e?.response?.data?.message || 'Règlement impossible.');
                            } finally {
                                setSaving(false);
                            }
                        }}
                    >
                        <Select
                            id={`settlement-${r.id}`}
                            label="Décision"
                            value={decisions[r.id] || 'restitution_beneficiaire'}
                            onChange={(e) =>
                                setDecisions({
                                    ...decisions,
                                    [r.id]: e.target.value,
                                })
                            }
                            options={[
                                {
                                    value: 'restitution_beneficiaire',
                                    label: 'Restitution intégrale au bénéficiaire',
                                },
                                {
                                    value: 'transfert_intervenant',
                                    label: 'Transfert intégral à l’intervenant',
                                },
                            ]}
                        />
                        <label htmlFor={`reason-${r.id}`} className="block text-sm">
                            Motif de la décision
                        </label>
                        <textarea
                            id={`reason-${r.id}`}
                            required
                            minLength={5}
                            className="w-full border rounded-xl p-3"
                            value={reasons[r.id] || ''}
                            onChange={(e) =>
                                setReasons({
                                    ...reasons,
                                    [r.id]: e.target.value,
                                })
                            }
                        />
                        <Button type="submit" isLoading={saving}>
                            Régler définitivement
                        </Button>
                    </form>
                </Card>
            ))}
            {!items.length && !error && <p>Aucune séance en attente de traitement.</p>}
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
