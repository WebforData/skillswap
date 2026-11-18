@extends('layouts.app')

@section('title', 'Modifier l\'Offre : ' . $offer->titre)

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
                        <i class="bi bi-pencil-square me-2 text-primary"></i>Modifier l'offre
                    </h4>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('offers.update', $offer) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="titre" class="form-label small fw-semibold">Titre de l'offre</label>
                            <input type="text" name="titre" id="titre" class="form-control @error('titre') is-invalid @enderror" value="{{ old('titre', $offer->titre) }}" required>
                            @error('titre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label small fw-semibold">Catégorie</label>
                                <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('category_id', $offer->category_id) == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->nom }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="competence" class="form-label small fw-semibold">Compétence clé</label>
                                <input type="text" name="competence" id="competence" class="form-control @error('competence') is-invalid @enderror" value="{{ old('competence', $offer->competence) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label small fw-semibold">Description</label>
                            <textarea name="description" id="description" rows="5" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $offer->description) }}</textarea>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="niveau" class="form-label small fw-semibold">Niveau</label>
                                <select name="niveau" id="niveau" class="form-select" required>
                                    <option value="debutant" {{ old('niveau', $offer->niveau) == 'debutant' ? 'selected' : '' }}>Débutant</option>
                                    <option value="intermediaire" {{ old('niveau', $offer->niveau) == 'intermediaire' ? 'selected' : '' }}>Intermédiaire</option>
                                    <option value="avance" {{ old('niveau', $offer->niveau) == 'avance' ? 'selected' : '' }}>Avancé</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="duree" class="form-label small fw-semibold">Durée (RG02)</label>
                                <select name="duree" id="duree" class="form-select" required>
                                    <option value="1" {{ old('duree', $offer->duree) == 1 ? 'selected' : '' }}>1 heure (1 crédit)</option>
                                    <option value="2" {{ old('duree', $offer->duree) == 2 ? 'selected' : '' }}>2 heures (2 crédits)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="mode" class="form-label small fw-semibold">Mode</label>
                                <select name="mode" id="mode" class="form-select" required>
                                    <option value="presentiel" {{ old('mode', $offer->mode) == 'presentiel' ? 'selected' : '' }}>Présentiel</option>
                                    <option value="distanciel" {{ old('mode', $offer->mode) == 'distanciel' ? 'selected' : '' }}>À distance</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label small fw-semibold">Statut de l'offre (RG04)</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="publiee" {{ old('status', $offer->status) == 'publiee' ? 'selected' : '' }}>Publiée</option>
                                <option value="brouillon" {{ old('status', $offer->status) == 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                                <option value="archivee" {{ old('status', $offer->status) == 'archivee' ? 'selected' : '' }}>Archivée (RG17)</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('offers.my') }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-brand px-4">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
