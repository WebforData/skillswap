import { useEffect, useState } from 'react';
import { studentApi } from '../../api/student.api';
import { Litige } from '../../types/admin.types';
import { AlertBanner, Button, Card, Badge } from '../../components/common';
export default function DisputesPage() {
    const [items, setItems] = useState<Litige[]>([]);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const [messages, setMessages] = useState<Record<number, string>>({});
    async function load() {
        try {
            setItems(await studentApi.disputes());
        } catch {
            setError('Impossible de charger vos litiges.');
        }
    }
    useEffect(() => {
        void load();
    }, []);
    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-bold">Mes litiges</h1>
            {error && <AlertBanner variant="error">{error}</AlertBanner>}
            {items.map((l) => (
                <Card title={`Litige #${l.id} — séance #${l.reservation_id}`} key={l.id}>
                    <div className="space-y-3">
                        <Badge status={l.statut} />
                        <p>{l.description}</p>
                        {l.statements?.map((s) => (
                            <div key={s.id} className="bg-slate-50 rounded-xl p-3">
                                <strong>
                                    {s.prenom} {s.nom}
                                </strong>
                                <p className="whitespace-pre-wrap">{s.message}</p>
                            </div>
                        ))}
                        {l.statut === 'resolu' ? (
                            <p className="font-semibold">
                                Décision :{' '}
                                {l.decision === 'transfert_intervenant'
                                    ? 'Transfert à l’intervenant'
                                    : 'Restitution au bénéficiaire'}{' '}
                                — {l.motif_decision}
                            </p>
                        ) : (
                            <form
                                className="space-y-2"
                                onSubmit={async (e) => {
                                    e.preventDefault();
                                    setSaving(true);
                                    setError('');
                                    try {
                                        await studentApi.statement(l.id, messages[l.id] || '');
                                        setMessages({
                                            ...messages,
                                            [l.id]: '',
                                        });
                                        await load();
                                    } catch (e: any) {
                                        setError(
                                            e?.response?.data?.message ||
                                                'Témoignage non enregistré.',
                                        );
                                    } finally {
                                        setSaving(false);
                                    }
                                }}
                            >
                                <label
                                    htmlFor={`statement-${l.id}`}
                                    className="block text-sm font-semibold"
                                >
                                    Votre témoignage
                                </label>
                                <textarea
                                    required
                                    minLength={10}
                                    maxLength={2000}
                                    id={`statement-${l.id}`}
                                    className="w-full border p-3 rounded-xl"
                                    value={messages[l.id] || ''}
                                    onChange={(e) =>
                                        setMessages({
                                            ...messages,
                                            [l.id]: e.target.value,
                                        })
                                    }
                                />
                                <Button type="submit" isLoading={saving}>
                                    Ajouter mon témoignage
                                </Button>
                            </form>
                        )}
                    </div>
                </Card>
            ))}
            {!items.length && !error && <p>Aucun litige.</p>}
        </div>
    );
}
