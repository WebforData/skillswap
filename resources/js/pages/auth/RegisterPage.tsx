/**
 * SkillSwap Campus — Page d'Inscription Étudiant (RG01, 2 crédits de bienvenue)
 */

import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
    GraduationCap,
    UserPlus,
    Mail,
    Lock,
    Eye,
    EyeOff,
    Coins,
    BookOpen,
    User as UserIcon,
} from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useNotification } from '../../contexts/NotificationContext';
import { Button, Input, Card, AlertBanner } from '../../components/common';
import axios from 'axios';

export const RegisterPage: React.FC = () => {
    const { register, isAuthenticated, user, error: authError, clearError } = useAuth();
    const { success } = useNotification();
    const navigate = useNavigate();

    // Redirection automatique si déjà connecté
    useEffect(() => {
        if (isAuthenticated && user) {
            navigate('/app', { replace: true });
        }
    }, [isAuthenticated, user, navigate]);

    // État du formulaire
    const [nom, setNom] = useState('');
    const [prenom, setPrenom] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [filiere, setFiliere] = useState('');
    const [competencesProposees, setCompetencesProposees] = useState('');
    const [competencesRecherchees, setCompetencesRecherchees] = useState('');

    const [showPassword, setShowPassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [globalError, setGlobalError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setFieldErrors({});
        setGlobalError(null);
        clearError();

        // Validation locale
        const errors: Record<string, string> = {};
        if (!nom.trim()) errors.nom = 'Le nom est obligatoire.';
        if (!prenom.trim()) errors.prenom = 'Le prénom est obligatoire.';
        if (!email.trim()) {
            errors.email = "L'adresse email est obligatoire.";
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
            errors.email = "L'adresse email doit être valide.";
        }
        if (!password) {
            errors.password = 'Le mot de passe est obligatoire.';
        } else if (password.length < 8) {
            errors.password = 'Le mot de passe doit comporter au moins 8 caractères.';
        }
        if (password !== passwordConfirmation) {
            errors.password_confirmation = 'La confirmation du mot de passe ne correspond pas.';
        }

        if (Object.keys(errors).length > 0) {
            setFieldErrors(errors);
            return;
        }

        setIsLoading(true);

        try {
            await register({
                nom: nom.trim(),
                prenom: prenom.trim(),
                email: email.trim(),
                password,
                password_confirmation: passwordConfirmation,
                filiere: filiere.trim() || undefined,
                competences_proposees: competencesProposees.trim() || undefined,
                competences_recherchees: competencesRecherchees.trim() || undefined,
            });

            success('Compte créé avec succès ! Vos 2 crédits de bienvenue ont été crédités (RG01).');
            navigate('/app', { replace: true });
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
                } else if (status === 429) {
                    setGlobalError(
                        data.message || 'Trop de tentatives. Veuillez patienter avant de réessayer.'
                    );
                } else {
                    setGlobalError(data.message || "Une erreur est survenue lors de l'inscription.");
                }
            } else {
                setGlobalError('Impossible de joindre le serveur. Vérifiez votre connexion.');
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="min-h-[calc(100vh-16rem)] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div className="w-full max-w-xl space-y-6">
                <div className="text-center">
                    <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 mb-3">
                        <GraduationCap className="w-7 h-7" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                        Rejoindre SkillSwap Campus
                    </h1>
                    <p className="mt-1.5 text-sm text-slate-500">
                        Partagez vos compétences et apprenez auprès d'autres étudiants
                    </p>
                </div>

                {/* Bannière d'incitation RG01 */}
                <div className="p-4 rounded-xl bg-amber-50 border border-amber-200/80 flex items-start gap-3 shadow-xs">
                    <div className="p-1.5 rounded-lg bg-amber-100 text-amber-700 shrink-0">
                        <Coins className="w-5 h-5" />
                    </div>
                    <div className="text-xs text-amber-900 leading-relaxed">
                        <span className="font-semibold">Bonus de bienvenue (Règle RG01) : </span>
                        À l'inscription, votre portefeuille est automatiquement crédité de{' '}
                        <span className="font-bold underline">2 crédits d'échange</span> pour vous
                        permettre de réserver vos premières séances sans attendre d'avoir enseigné.
                    </div>
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

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Input
                                id="register-nom"
                                label="Nom"
                                type="text"
                                value={nom}
                                onChange={(e) => setNom(e.target.value)}
                                placeholder="Dupont"
                                icon={<UserIcon className="w-4 h-4 text-slate-400" />}
                                error={fieldErrors.nom}
                                required
                                autoComplete="family-name"
                                disabled={isLoading}
                            />

                            <Input
                                id="register-prenom"
                                label="Prénom"
                                type="text"
                                value={prenom}
                                onChange={(e) => setPrenom(e.target.value)}
                                placeholder="Alexandre"
                                icon={<UserIcon className="w-4 h-4 text-slate-400" />}
                                error={fieldErrors.prenom}
                                required
                                autoComplete="given-name"
                                disabled={isLoading}
                            />
                        </div>

                        <Input
                            id="register-email"
                            label="Adresse email universitaire"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="alexandre.dupont@etudiant.univ.fr"
                            icon={<Mail className="w-4 h-4 text-slate-400" />}
                            error={fieldErrors.email}
                            helperText="Une adresse valide est requise pour recevoir vos notifications de séances."
                            required
                            autoComplete="email"
                            disabled={isLoading}
                        />

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Input
                                id="register-password"
                                label="Mot de passe"
                                type={showPassword ? 'text' : 'password'}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="8 caractères minimum"
                                icon={<Lock className="w-4 h-4 text-slate-400" />}
                                error={fieldErrors.password}
                                required
                                autoComplete="new-password"
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

                            <Input
                                id="register-password-confirm"
                                label="Confirmer le mot de passe"
                                type={showPassword ? 'text' : 'password'}
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                placeholder="Répétez le mot de passe"
                                icon={<Lock className="w-4 h-4 text-slate-400" />}
                                error={fieldErrors.password_confirmation}
                                required
                                autoComplete="new-password"
                                disabled={isLoading}
                            />
                        </div>

                        <Input
                            id="register-filiere"
                            label="Filière / Département d'études (optionnel)"
                            type="text"
                            value={filiere}
                            onChange={(e) => setFiliere(e.target.value)}
                            placeholder="Ex : Licence Informatique, Master Finance..."
                            icon={<BookOpen className="w-4 h-4 text-slate-400" />}
                            error={fieldErrors.filiere}
                            disabled={isLoading}
                        />

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <div>
                                <label
                                    htmlFor="register-proposees"
                                    className="block text-xs font-semibold text-slate-700 mb-1"
                                >
                                    Compétences que vous pouvez partager
                                </label>
                                <textarea
                                    id="register-proposees"
                                    rows={2}
                                    value={competencesProposees}
                                    onChange={(e) => setCompetencesProposees(e.target.value)}
                                    placeholder="Ex : Algorithmique Python, Anglais B2, Guitare débutant..."
                                    className="w-full text-xs rounded-xl border border-slate-200 bg-white p-2.5 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all resize-none"
                                    disabled={isLoading}
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="register-recherchees"
                                    className="block text-xs font-semibold text-slate-700 mb-1"
                                >
                                    Compétences que vous recherchez
                                </label>
                                <textarea
                                    id="register-recherchees"
                                    rows={2}
                                    value={competencesRecherchees}
                                    onChange={(e) => setCompetencesRecherchees(e.target.value)}
                                    placeholder="Ex : Statistiques R, Espagnol conversationnel..."
                                    className="w-full text-xs rounded-xl border border-slate-200 bg-white p-2.5 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all resize-none"
                                    disabled={isLoading}
                                />
                            </div>
                        </div>

                        <div className="pt-3">
                            <Button
                                type="submit"
                                variant="primary"
                                size="md"
                                isFullWidth
                                isLoading={isLoading}
                                icon={<UserPlus className="w-4 h-4" />}
                            >
                                Créer mon compte (+2 crédits)
                            </Button>
                        </div>
                    </form>

                    <div className="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                        Déjà inscrit ?{' '}
                        <Link
                            to="/connexion"
                            className="font-semibold text-indigo-600 hover:text-indigo-700 transition-colors"
                        >
                            Se connecter
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    );
};
export default RegisterPage;
