/**
 * SkillSwap Campus — Point d'Entrée & Routage SPA Intégré aux Layouts
 */

import React, { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate, Link } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { NotificationProvider } from './contexts/NotificationContext';
import { ProtectedRoute } from './routes/ProtectedRoute';
import { AdminRoute } from './routes/AdminRoute';
import { GuestLayout, AppLayout, AdminLayout } from './layouts';
import {
    Button,
    Card,
} from './components/common';
import {
    BookOpen,
    CheckCircle2,
    Sparkles,
} from 'lucide-react';

/* -------------------------------------------------------------------------- */
/* Chargement dynamique (React.lazy Code Splitting)                           */
/* -------------------------------------------------------------------------- */
const DashboardPage = lazy(() => import('./pages/student/DashboardPage'));
const EvaluationsPage = lazy(() => import('./pages/student/EvaluationsPage'));
const NotificationsPage = lazy(() => import('./pages/student/NotificationsPage'));
const DisputesPage = lazy(() => import('./pages/student/DisputesPage'));
const PublicReviewsPage = lazy(() => import('./pages/student/PublicReviewsPage'));
const LoginPage = lazy(() => import('./pages/auth/LoginPage').then(m => ({ default: m.LoginPage })));
const RegisterPage = lazy(() => import('./pages/auth/RegisterPage').then(m => ({ default: m.RegisterPage })));
const CataloguePage = lazy(() => import('./pages/student/CataloguePage').then(m => ({ default: m.CataloguePage })));
const OffreDetailPage = lazy(() => import('./pages/student/OffreDetailPage').then(m => ({ default: m.OffreDetailPage })));
const MesOffresPage = lazy(() => import('./pages/student/MesOffresPage').then(m => ({ default: m.MesOffresPage })));
const PlanningPage = lazy(() => import('./pages/student/PlanningPage').then(m => ({ default: m.PlanningPage })));
const ReservationsPage = lazy(() => import('./pages/student/ReservationsPage').then(m => ({ default: m.ReservationsPage })));
const ProfilePage = lazy(() => import('./pages/student/ProfilePage').then(m => ({ default: m.ProfilePage })));
const AdminSessionsPage = lazy(() => import('./pages/admin/AdminSessionsPage'));
const AdminDashboardPage = lazy(() => import('./pages/admin/AdminDashboardPage').then(m => ({ default: m.AdminDashboardPage })));
const AdminUsersPage = lazy(() => import('./pages/admin/AdminUsersPage').then(m => ({ default: m.AdminUsersPage })));
const AdminCategoriesPage = lazy(() => import('./pages/admin/AdminCategoriesPage').then(m => ({ default: m.AdminCategoriesPage })));
const AdminOffersPage = lazy(() => import('./pages/admin/AdminOffersPage').then(m => ({ default: m.AdminOffersPage })));
const AdminDisputesPage = lazy(() => import('./pages/admin/AdminDisputesPage').then(m => ({ default: m.AdminDisputesPage })));

/**
 * Indicateur de chargement différé fluide et accessible
 */
const PageLoader: React.FC = () => (
    <div className="flex-1 flex flex-col items-center justify-center min-h-[50vh] p-8 text-center" role="status" aria-live="polite">
        <div className="w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-4" />
        <p className="text-sm font-medium text-slate-600">Chargement de la page...</p>
    </div>
);

/* -------------------------------------------------------------------------- */
/* Page d'Accueil Publique (Guest)                                           */
/* -------------------------------------------------------------------------- */
const HomePage: React.FC = () => {
    return (
        <div className="flex-1 flex flex-col justify-center items-center py-16 px-4 sm:px-6 lg:px-8 text-center max-w-4xl mx-auto">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-50 border border-indigo-200/80 text-indigo-700 text-xs font-semibold mb-6">
                <Sparkles className="w-3.5 h-3.5" />
                <span>Plateforme universitaire d'échange de compétences</span>
            </div>

            <h1 className="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">
                Apprenez, partagez, progressez :{' '}
                <span className="text-indigo-600">1 heure donnée = 1 heure reçue</span>
            </h1>

            <p className="text-base text-slate-600 max-w-2xl mb-8 leading-relaxed">
                SkillSwap Campus permet aux étudiants d'échanger leurs savoirs de manière équitable.
                Chaque séance d'aide réalisée vous fait gagner du temps de cours dans une autre matière.
            </p>

            <div className="flex flex-col sm:flex-row items-center gap-3">
                <Link to="/inscription">
                    <Button variant="primary" size="lg" icon={<CheckCircle2 className="w-5 h-5" />}>
                        Créer un compte (+2 crédits offerts)
                    </Button>
                </Link>
                <Link to="/catalogue">
                    <Button variant="outline" size="lg" icon={<BookOpen className="w-5 h-5" />}>
                        Explorer le catalogue d'offres
                    </Button>
                </Link>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 mt-16 w-full text-left">
                <Card title="Équité garantie (RG01)">
                    <p className="text-xs text-slate-600 leading-relaxed">
                        Chaque heure enseignée donne droit à exactement 1 crédit. Aucune spéculation financière.
                    </p>
                </Card>
                <Card title="Sécurité des séances (RG19)">
                    <p className="text-xs text-slate-600 leading-relaxed">
                        Lieux précis et liens visio strictement protégés et révélés uniquement aux participants confirmés.
                    </p>
                </Card>
                <Card title="Tiers de confiance">
                    <p className="text-xs text-slate-600 leading-relaxed">
                        Double confirmation post-séance et arbitrage administratif en cas de désaccord.
                    </p>
                </Card>
            </div>
        </div>
    );
};

/* -------------------------------------------------------------------------- */
/* Application Globale avec Routage Emboîté                                   */
/* -------------------------------------------------------------------------- */
export const App: React.FC = () => {
    return (
        <BrowserRouter>
            <AuthProvider>
                <NotificationProvider>
                    <Suspense fallback={<PageLoader />}>
                        <Routes>
                            {/* 1. Routes Publiques avec GuestLayout */}
                            <Route element={<GuestLayout />}>
                                <Route path="/" element={<HomePage />} />
                                <Route path="/catalogue" element={<CataloguePage />} />
                                <Route path="/offres/:id" element={<OffreDetailPage />} />
                                <Route path="/profils/:id/avis" element={<PublicReviewsPage />} />
                                <Route path="/connexion" element={<LoginPage />} />
                                <Route path="/inscription" element={<RegisterPage />} />
                            </Route>

                            {/* Redirections pratiques */}
                            <Route path="/offres" element={<Navigate to="/catalogue" replace />} />
                            <Route path="/tableau-de-bord" element={<Navigate to="/app" replace />} />

                            {/* 2. Espace Étudiant Protégé avec AppLayout */}
                            <Route
                                path="/app"
                                element={
                                    <ProtectedRoute>
                                        <AppLayout />
                                    </ProtectedRoute>
                                }
                            >
                                <Route index element={<DashboardPage />} />
                                <Route path="catalogue" element={<CataloguePage />} />
                                <Route path="offres/:id" element={<OffreDetailPage />} />
                                <Route path="mes-offres" element={<MesOffresPage />} />
                                <Route path="planning" element={<PlanningPage />} />
                                <Route path="reservations" element={<ReservationsPage />} />
                                <Route path="evaluations" element={<EvaluationsPage />} />
                                <Route path="notifications" element={<NotificationsPage />} />
                                <Route path="litiges" element={<DisputesPage />} />
                                <Route path="profil" element={<ProfilePage />} />
                            </Route>

                            {/* 3. Espace Administrateur Protégé avec AdminLayout */}
                            <Route
                                path="/admin"
                                element={
                                    <AdminRoute>
                                        <AdminLayout />
                                    </AdminRoute>
                                }
                            >
                                <Route index element={<AdminDashboardPage />} />
                                <Route path="users" element={<AdminUsersPage />} />
                                <Route path="seances" element={<AdminSessionsPage />} />
                                <Route path="categories" element={<AdminCategoriesPage />} />
                                <Route path="offres" element={<AdminOffersPage />} />
                                <Route path="litiges" element={<AdminDisputesPage />} />
                            </Route>

                            {/* Redirection des Routes Inconnues */}
                            <Route path="*" element={<Navigate to="/" replace />} />
                        </Routes>
                    </Suspense>
                </NotificationProvider>
            </AuthProvider>
        </BrowserRouter>
    );
};

export default App;
