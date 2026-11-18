/**
 * SkillSwap Campus — Page de Connexion (Sanctum Bearer Token, RG18)
 */

import React, { useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Mail, Lock, Eye, EyeOff, LogIn, GraduationCap } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useNotification } from '../../contexts/NotificationContext';
import { Button, Input, Card, AlertBanner } from '../../components/common';
import axios from 'axios';

export const LoginPage: React.FC = () => {
    const { login, isAuthenticated, user, error: authError, clearError } = useAuth();
    const { success } = useNotification();
    const navigate = useNavigate();
    const location = useLocation();

    // Redirection automatique si déjà connecté
    useEffect(() => {
        if (isAuthenticated && user) {
            const destination = (location.state as any)?.from?.pathname ||
                (user.role === 'administrateur' ? '/admin' : '/app');
            navigate(destination, { replace: true });
        }
    }, [isAuthenticated, user, navigate, location]);

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [globalError, setGlobalError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setFieldErrors({});
        setGlobalError(null);
        clearError();

        // Validation locale simple
        const errors: Record<string, string> = {};
        if (!email.trim()) {
            errors.email = "L'adresse email est obligatoire.";
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
            errors.email = "Veuillez saisir une adresse email valide.";
        }
        if (!password) {
            errors.password = "Le mot de passe est obligatoire.";
        }

        if (Object.keys(errors).length > 0) {
            setFieldErrors(errors);
            return;
        }

        setIsLoading(true);

        try {
            const loggedUser = await login({ email: email.trim(), password });
            success(`Bienvenue, ${loggedUser.prenom} ! Connexion réussie.`);

            const target = (location.state as any)?.from?.pathname ||
                (loggedUser.role === 'administrateur' ? '/admin' : '/app');
            navigate(target, { replace: true });
        } catch (err: any) {
            if (axios.isAxiosError(err) && err.response) {
                const status = err.response.status;
                const data = err.response.data;

                if (status === 422 && data.errors) {
                    const mapped: Record<string, string> = {};
                    Object.keys(data.errors).forEach((key) => {
                        mapped[key] = data.errors[key][0];
                    });
                    setFieldErrors(mapped);
                } else if (status === 401) {
                    setGlobalError("Identifiants incorrects. Veuillez vérifier votre adresse email et votre mot de passe.");
                } else if (status === 403) {
                    setGlobalError(
                        data.message || "Votre compte étudiant est actuellement désactivé (RG18). Veuillez contacter le support universitaire."
                    );
                } else if (status === 429) {
                    setGlobalError(
                        data.message || "Trop de tentatives de connexion infructueuses. Veuillez patienter avant de réessayer."
                    );
                } else {
                    setGlobalError(data.message || "Une erreur est survenue lors de la connexion.");
                }
            } else {
                setGlobalError("Impossible de joindre le serveur. Vérifiez votre connexion.");
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="min-h-[calc(100vh-16rem)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div className="w-full max-w-md space-y-6">
                <div className="text-center">
                    <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 mb-3">
                        <GraduationCap className="w-7 h-7" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                        Connexion à SkillSwap
                    </h1>
                    <p className="mt-1.5 text-sm text-slate-500">
                        Accédez à votre espace étudiant ou administratif
                    </p>
                </div>

                <Card>
                    <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                        {(globalError || authError) && (
                            <AlertBanner
                                variant="error"
                                message={globalError || authError || ''}
                                onClose={() => {
                                    setGlobalError(null);
                                    clearError();
                                }}
                            />
                        )}

                        <Input
                            id="login-email"
                            label="Adresse email universitaire"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="etudiant@universite.fr"
                            icon={<Mail className="w-4 h-4 text-slate-400" />}
                            error={fieldErrors.email}
                            required
                            autoComplete="email"
                            disabled={isLoading}
                        />

                        <Input
                            id="login-password"
                            label="Mot de passe"
                            type={showPassword ? 'text' : 'password'}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="••••••••"
                            icon={<Lock className="w-4 h-4 text-slate-400" />}
                            error={fieldErrors.password}
                            required
                            autoComplete="current-password"
                            disabled={isLoading}
                            rightElement={
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors"
                                    aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <EyeOff className="w-4 h-4" />
                                    ) : (
                                        <Eye className="w-4 h-4" />
                                    )}
                                </button>
                            }
                        />

                        <div className="pt-2">
                            <Button
                                type="submit"
                                variant="primary"
                                size="md"
                                isFullWidth
                                isLoading={isLoading}
                                icon={<LogIn className="w-4 h-4" />}
                            >
                                Se connecter
                            </Button>
                        </div>
                    </form>

                    <div className="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                        Pas encore inscrit ?{' '}
                        <Link
                            to="/inscription"
                            className="font-semibold text-indigo-600 hover:text-indigo-700 transition-colors"
                        >
                            Créer un compte (+2 crédits offerts)
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    );
};
export default LoginPage;
