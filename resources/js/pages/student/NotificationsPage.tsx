import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentApi, PersonalNotification } from '../../api/student.api';
import { AlertBanner, Button, Card, Pagination } from '../../components/common';
export default function NotificationsPage() {
    const [items, setItems] = useState<PersonalNotification[]>([]);
    const [page, setPage] = useState(1);
    const [last, setLast] = useState(1);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState('');
    async function load() {
        try {
            const d = await studentApi.notifications(page);
            setItems(d.data);
            setLast(d.last_page);
            setTotal(d.total);
            setError('');
        } catch {
            setError('Notifications indisponibles.');
        }
    }
    useEffect(() => {
        void load();
    }, [page]);
    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-bold">Mes notifications</h1>
            {error && <AlertBanner variant="error">{error}</AlertBanner>}
            {items.map((n) => (
                <Card key={n.id} title={n.title}>
                    <div className="space-y-2">
                        <p>{n.message}</p>
                        <p className="text-xs text-slate-500">
                            {new Date(n.created_at).toLocaleString('fr-FR')} ·{' '}
                            {n.is_read ? 'Lue' : 'Non lue'}
                        </p>
                        <div className="flex gap-4 items-center">
                            {n.reservation_id && (
                                <Link className="text-indigo-700 underline" to="/app/reservations">
                                    Voir mes réservations
                                </Link>
                            )}
                            {!n.is_read && (
                                <Button
                                    size="sm"
                                    onClick={async () => {
                                        try {
                                            await studentApi.readNotification(n.id);
                                            await load();
                                        } catch {
                                            setError('Impossible de marquer la notification.');
                                        }
                                    }}
                                >
                                    Marquer comme lue
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>
            ))}
            {!items.length && !error && <p>Aucune notification.</p>}
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
