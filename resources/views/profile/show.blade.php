@extends('layouts.app')

@section('title', 'Mon Profil & Compétences')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 col-xl-2 d-none d-lg-block">
            @include('layouts.sidebar')
        </div>

        <!-- Contenu principal -->
        <div class="col-lg-9 col-xl-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">Mon Profil & Compétences</h3>
                    <p class="text-muted small mb-0">Gérez vos informations universitaires, compétences proposées et compétences recherchées.</p>
                </div>
            </div>

            <div class="row g-4">
                <!-- Colonne Gauche : Formulaire de modification -->
                <div class="col-md-7">
                    <div class="card card-custom">
                        <div class="card-header card-header-custom">
                            <i class="bi bi-pencil-square me-2 text-primary"></i>Modifier mes informations
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('profile.update') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="prenom" class="form-label small fw-semibold">Prénom</label>
                                        <input type="text" name="prenom" id="prenom" class="form-control" value="{{ old('prenom', $user->prenom) }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nom" class="form-label small fw-semibold">Nom</label>
                                        <input type="text" name="nom" id="nom" class="form-control" value="{{ old('nom', $user->nom) }}" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label small fw-semibold">Adresse électronique (Identifiant unique - RG01)</label>
                                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-7">
                                        <label for="filiere" class="form-label small fw-semibold">Filière / Département</label>
                                        <input type="text" name="filiere" id="filiere" class="form-control" value="{{ old('filiere', $user->filiere) }}">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="niveau_etudes" class="form-label small fw-semibold">Niveau</label>
                                        <input type="text" name="niveau_etudes" id="niveau_etudes" class="form-control" value="{{ old('niveau_etudes', $user->niveau_etudes) }}">
                                    </div>
                                </div>

                                <hr class="my-4">

                                <h6 class="fw-bold mb-3 text-dark">
                                    <i class="bi bi-lightbulb-fill text-warning me-1"></i> Banque de compétences
                                </h6>

                                <div class="mb-3">
                                    <label for="skills_proposees" class="form-label small fw-semibold">
                                        Compétences proposées <span class="text-muted fw-normal">(séparées par des virgules)</span>
                                    </label>
                                    <input type="text" name="skills_proposees" id="skills_proposees" class="form-control" 
                                           value="{{ old('skills_proposees', $user->skillsProposees->pluck('competence')->join(', ')) }}"
                                           placeholder="Ex: Python, Laravel, SQL, Git">
                                    <div class="form-text small">Les matières ou outils que vous pouvez enseigner ou partager.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="skills_recherchees" class="form-label small fw-semibold">
                                        Compétences recherchées <span class="text-muted fw-normal">(séparées par des virgules)</span>
                                    </label>
                                    <input type="text" name="skills_recherchees" id="skills_recherchees" class="form-control" 
                                           value="{{ old('skills_recherchees', $user->skillsRecherchees()->pluck('competence')->join(', ')) }}"
                                           placeholder="Ex: Anglais oral, Machine Learning, UI Design">
                                    <div class="form-text small">Utilisé pour alimenter automatiquement vos <strong>suggestions de séances</strong>.</div>
                                </div>

                                <button type="submit" class="btn btn-brand px-4">
                                    <i class="bi bi-check2-circle me-1"></i> Enregistrer les modifications
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Colonne Droite : Synthèse & Évaluations reçues -->
                <div class="col-md-5">
                    <!-- Carte Résumé Étudiant -->
                    <div class="card card-custom mb-4">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 72px; height: 72px; font-size: 2rem; font-weight: 700;">
                                {{ strtoupper(substr($user->prenom, 0, 1)) }}
                            </div>
                            <h5 class="fw-bold mb-1">{{ $user->nom_complet }}</h5>
                            <p class="text-muted small mb-2">{{ $user->filiere ?? 'Filière non renseignée' }} &bull; {{ $user->niveau_etudes ?? 'Étudiant' }}</p>

                            <!-- Note moyenne (RG15) -->
                            <div class="d-inline-flex align-items-center gap-1 bg-light border px-3 py-1 rounded-pill mb-3">
                                <i class="bi bi-star-fill text-warning"></i>
                                <span class="fw-bold">{{ $user->note_moyenne }} / 5</span>
                                <span class="text-muted small">({{ $user->nombre_avis }} avis)</span>
                            </div>

                            <div class="row g-2 border-top pt-3 text-start">
                                <div class="col-6">
                                    <div class="small text-muted">Solde disponible :</div>
                                    <div class="fw-bold text-success fs-5">{{ $user->solde_disponible }} heure(s)</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Solde bloqué :</div>
                                    <div class="fw-bold text-warning fs-5">{{ $user->solde_bloque }} heure(s)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Évaluations reçues -->
                    <div class="card card-custom">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-chat-heart me-2 text-danger"></i>Avis reçus des pairs</span>
                            <span class="badge bg-secondary">{{ $user->nombre_avis }}</span>
                        </div>
                        <div class="card-body p-3" style="max-height: 400px; overflow-y: auto;">
                            @forelse($user->evaluationsReceived as $eval)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="small">{{ $eval->evaluator->nom_complet }}</strong>
                                        <div class="text-warning small">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="bi bi-star{{ $i <= $eval->note ? '-fill' : '' }}"></i>
                                            @endfor
                                        </div>
                                    </div>
                                    <p class="small text-muted mb-1">{{ $eval->commentaire ?? 'Pas de commentaire textuel.' }}</p>
                                    <div class="text-end text-muted" style="font-size: 0.7rem;">{{ $eval->created_at->format('d/m/Y') }}</div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-star text-secondary fs-3 d-block mb-1"></i>
                                    Aucune évaluation reçue pour le moment. Réalisez des séances pour récolter des avis !
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
