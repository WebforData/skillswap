@extends('layouts.app')

@section('title', 'Proposer une Offre de Compétence')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-3">
                <a href="{{ route('offers.my') }}" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left me-1"></i> Retour à mes offres
                </a>
            </div>

            <div class="card card-custom">
                <div class="card-header card-header-custom">
                    <h4 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>Proposer une nouvelle compétence
                    </h4>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('offers.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="titre" class="form-label small fw-semibold">Titre de l'offre</label>
                            <input type="text" name="titre" id="titre" class="form-control @error('titre') is-invalid @enderror" value="{{ old('titre') }}" placeholder="Ex: Initiation complète à Python & structures de données" required>
                            @error('titre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label small fw-semibold">Catégorie</label>
                                <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                    <option value="">Sélectionnez une catégorie</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->nom }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="competence" class="form-label small fw-semibold">Compétence clé</label>
                                <input type="text" name="competence" id="competence" class="form-control @error('competence') is-invalid @enderror" value="{{ old('competence') }}" placeholder="Ex: Python, Algèbre, Anglais oral" required>
                                @error('competence') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label small fw-semibold">Description détaillée du programme</label>
                            <textarea name="description" id="description" rows="5" class="form-control @error('description') is-invalid @enderror" placeholder="Précisez ce que l'étudiant apprendra, les prérequis éventuels et le déroulement de la séance..." required>{{ old('description') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="niveau" class="form-label small fw-semibold">Niveau ciblé</label>
                                <select name="niveau" id="niveau" class="form-select @error('niveau') is-invalid @enderror" required>
                                    <option value="debutant" {{ old('niveau') == 'debutant' ? 'selected' : '' }}>Débutant</option>
                                    <option value="intermediaire" {{ old('niveau', 'intermediaire') == 'intermediaire' ? 'selected' : '' }}>Intermédiaire</option>
                                    <option value="avance" {{ old('niveau') == 'avance' ? 'selected' : '' }}>Avancé</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <!-- RG02: Durée stricte 1 ou 2 heures -->
                                <label for="duree" class="form-label small fw-semibold">Durée de la séance (RG02)</label>
                                <select name="duree" id="duree" class="form-select @error('duree') is-invalid @enderror" required>
                                    <option value="1" {{ old('duree') == '1' ? 'selected' : '' }}>1 heure (1 crédit)</option>
                                    <option value="2" {{ old('duree') == '2' ? 'selected' : '' }}>2 heures (2 crédits)</option>
                                </select>
                                <div class="form-text small">1 heure = 1 crédit de temps.</div>
                            </div>

                            <div class="col-md-4">
                                <label for="mode" class="form-label small fw-semibold">Mode de déroulement</label>
                                <select name="mode" id="mode" class="form-select @error('mode') is-invalid @enderror" required>
                                    <option value="presentiel" {{ old('mode') == 'presentiel' ? 'selected' : '' }}>Présentiel (sur campus)</option>
                                    <option value="distanciel" {{ old('mode') == 'distanciel' ? 'selected' : '' }}>À distance (visio)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <!-- RG04: Statut de l'offre -->
                            <label for="status" class="form-label small fw-semibold">Statut initial (RG04)</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="publiee" {{ old('status', 'publiee') == 'publiee' ? 'selected' : '' }}>Publiée (immédiatement visible pour les réservations)</option>
                                <option value="brouillon" {{ old('status') == 'brouillon' ? 'selected' : '' }}>Brouillon (non visible publiquement)</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('offers.my') }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-brand px-4">
                                <i class="bi bi-check2-circle me-1"></i> Créer l'offre & ajouter des créneaux
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
