import { useEffect, useState } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { useNotification } from '../../hooks/useNotification';
import { studentApi, Movement } from '../../api/student.api';
import { AlertBanner, Button, Card, Input, Pagination } from '../../components/common';
import { User } from '../../types/auth.types';
export function ProfileTools() {
    const { user, refreshUser } = useAuth();
    const { success } = useNotification();
    const [form, setForm] = useState<Partial<User>>({});
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [movements, setMovements] = useState<Movement[]>([]);
    const [page, setPage] = useState(1);
    const [last, setLast] = useState(1);
    const [total, setTotal] = useState(0);
    const [historyError, setHistoryError] = useState('');
    useEffect(() => {
        if (user)
            setForm({
                nom: user.nom,
                prenom: user.prenom,
                email: user.email,
                filiere: user.filiere || '',
                competences_proposees: user.competences_proposees || '',
                competences_recherchees: user.competences_recherchees || '',
            });
    }, [user]);
    useEffect(() => {
        studentApi
            .history(page)
            .then((d) => {
                setMovements(d.data);
                setLast(d.last_page);
                setTotal(d.total);
                setHistoryError('');
            })
            .catch(() => setHistoryError('Historique indisponible.'));
    }, [page, user]);
    async function save(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            await studentApi.updateProfile(form);
            await refreshUser();
            success('Profil enregistré.');
        } catch (e: any) {
            setError(
                Object.values(e?.response?.data?.errors || {})
                    .flat()
                    .join(' ') || 'Impossible de modifier le profil.',
            );
        } finally {
            setSaving(false);
        }
    }
    return (
        <div className="space-y-6">
            <Card title="Modifier mon profil">
                <form className="space-y-4" onSubmit={save}>
                    {error && <AlertBanner variant="error">{error}</AlertBanner>}
                    <div className="grid sm:grid-cols-2 gap-4">
                        {(
                            [
                                ['prenom', 'Prénom'],
                                ['nom', 'Nom'],
                                ['email', 'Adresse électronique'],
                                ['filiere', 'Filière'],
                                ['competences_proposees', 'Compétences proposées'],
                                ['competences_recherchees', 'Compétences recherchées'],
                            ] as const
                        ).map(([key, label]) => (
                            <Input
                                key={key}
                                id={`profile-${key}`}
                                label={label}
                                type={key === 'email' ? 'email' : 'text'}
                                required={['nom', 'prenom', 'email'].includes(key)}
                                value={String(form[key] ?? '')}
                                onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                            />
                        ))}
                    </div>
                    <p className="text-xs text-slate-500">
                        Séparez les compétences par des virgules.
                    </p>
                    <Button type="submit" isLoading={saving}>
                        Enregistrer le profil
                    </Button>
                </form>
            </Card>
            <Card title="Historique des crédits">
                {historyError && <AlertBanner variant="error">{historyError}</AlertBanner>}
                <div className="overflow-x-auto">
                    <table className="w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th className="p-2">Date</th>
                                <th className="p-2">Type</th>
                                <th className="p-2">Quantité</th>
                                <th className="p-2">Séance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.map((m) => (
                                <tr key={m.id} className="border-t">
                                    <td className="p-2">
                                        {new Date(m.date_mouvement).toLocaleString('fr-FR')}
                                    </td>
                                    <td className="p-2">{m.type.replaceAll('_', ' ')}</td>
                                    <td className="p-2">{m.quantite}</td>
                                    <td className="p-2">
                                        {m.reservation?.creneau?.offre?.titre ||
                                            (m.reservation_id
                                                ? `Séance #${m.reservation_id}`
                                                : 'Bienvenue')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!movements.length && !historyError && <p>Aucun mouvement.</p>}
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
