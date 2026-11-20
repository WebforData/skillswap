import { useEffect, useState } from 'react';
import { evaluationsApi } from '../../api/evaluations.api';
import { planningApi } from '../../api/planning.api';
import { Evaluation } from '../../types/evaluation.types';
import { PlanningItem } from '../../types/reservation.types';
import { AlertBanner, Button, Card, Select, Pagination } from '../../components/common';
export default function EvaluationsPage() {
    const [eligible, setEligible] = useState<PlanningItem[]>([]);
    const [given, setGiven] = useState<Evaluation[]>([]);
    const [session, setSession] = useState('');
    const [note, setNote] = useState('5');
    const [comment, setComment] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [saving, setSaving] = useState(false);
    const [page, setPage] = useState(1);
    const [last, setLast] = useState(1);
    const [total, setTotal] = useState(0);
    async function load() {
        try {
            const [p, e] = await Promise.all([
                planningApi.getPlanning({}),
                evaluationsApi.getDonnees(page),
            ]);
            setEligible(p.planning.filter((i) => i.actions_possibles.peut_evaluer));
            setGiven(e.data);
            setLast(e.last_page);
            setTotal(e.total);
        } catch {
            setError('Impossible de charger les évaluations.');
        }
    }
    useEffect(() => {
        void load();
    }, [page]);
    async function submit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setError('');
        setSuccess('');
        try {
            await evaluationsApi.evaluer({
                reservation_id: Number(session),
                note: Number(note),
                commentaire: comment,
            });
            setSession('');
            setComment('');
            setSuccess('Votre évaluation a été enregistrée.');
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message || 'Évaluation impossible.');
        } finally {
            setSaving(false);
        }
    }
    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold">Évaluations & avis</h1>
            {error && <AlertBanner variant="error">{error}</AlertBanner>}
            {success && <AlertBanner variant="success">{success}</AlertBanner>}
            <Card title="Évaluer une séance réalisée">
                {eligible.length ? (
                    <form onSubmit={submit} className="space-y-4">
                        <Select
                            id="review-session"
                            label="Séance"
                            required
                            value={session}
                            onChange={(e) => setSession(e.target.value)}
                            options={[
                                { value: '', label: 'Choisir une séance' },
                                ...eligible.map((i) => ({
                                    value: i.reservation_id,
                                    label: `${i.offre?.titre} — ${i.autre_participant?.prenom} (${i.creneau.date})`,
                                })),
                            ]}
                        />
                        <Select
                            id="review-note"
                            label="Note sur 5"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            options={[1, 2, 3, 4, 5].map((n) => ({
                                value: n,
                                label: `${n} / 5`,
                            }))}
                        />
                        <label className="block text-sm" htmlFor="review-comment">
                            Commentaire
                        </label>
                        <textarea
                            id="review-comment"
                            className="w-full border rounded-xl p-3"
                            maxLength={2000}
                            value={comment}
                            onChange={(e) => setComment(e.target.value)}
                        />
                        <Button type="submit" isLoading={saving}>
                            Publier mon évaluation
                        </Button>
                    </form>
                ) : (
                    <p className="text-sm text-slate-600">
                        Aucune séance réalisée ne reste à évaluer.
                    </p>
                )}
            </Card>
            <Card title="Mes évaluations publiées">
                <div className="space-y-4">
                    {given.map((e) => (
                        <div key={e.id} className="border-b pb-3">
                            <p className="font-semibold">
                                {e.cible?.prenom} {e.cible?.nom} · {e.note} / 5
                            </p>
                            <p>{e.commentaire}</p>
                        </div>
                    ))}
                    {!given.length && <p>Aucune évaluation publiée.</p>}
                </div>
                {last > 1 && (
                    <Pagination
                        currentPage={page}
                        lastPage={last}
                        total={total}
                        onPageChange={setPage}
                    />
                )}
            </Card>
        </div>
    );
}
