@extends('layouts.app')

@section('title', 'Catalogue des Offres de Compétences')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">Catalogue des Compétences Universitaires</h2>
            <p class="text-muted small mb-0">Recherchez et réservez des formations d'une ou deux heures avec vos pairs.</p>
        </div>
        @auth
            <a href="{{ route('offers.create') }}" class="btn btn-brand px-3 shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Proposer une séance
            </a>
        @endauth
    </div>

    <!-- Filtres & Recherche -->
    <div class="card card-custom mb-4 p-3 border-0 shadow-sm">
        <form action="{{ route('offers.index') }}" method="GET">
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Rechercher compétence, mot-clé..." value="{{ request('q') }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="">Toutes les catégories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="niveau" class="form-select">
                        <option value="">Tous niveaux</option>
                        <option value="debutant" {{ request('niveau') == 'debutant' ? 'selected' : '' }}>Débutant</option>
                        <option value="intermediaire" {{ request('niveau') == 'intermediaire' ? 'selected' : '' }}>Intermédiaire</option>
                        <option value="avance" {{ request('niveau') == 'avance' ? 'selected' : '' }}>Avancé</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="mode" class="form-select">
                        <option value="">Tous modes</option>
                        <option value="presentiel" {{ request('mode') == 'presentiel' ? 'selected' : '' }}>Présentiel</option>
                        <option value="distanciel" {{ request('mode') == 'distanciel' ? 'selected' : '' }}>À distance</option>
                    </select>
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
            </div>
            @if(request()->anyFilled(['q', 'category_id', 'niveau', 'mode']))
                <div class="mt-2 text-end">
                    <a href="{{ route('offers.index') }}" class="small text-danger text-decoration-none">
                        <i class="bi bi-x-circle me-1"></i> Réinitialiser les filtres
                    </a>
                </div>
            @endif
        </form>
    </div>

    <!-- Grille des offres -->
    <div class="row g-4 mb-4">
        @forelse($offers as $offer)
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom h-100 d-flex flex-column">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                {{ $offer->category->nom }}
                            </span>
                            <span class="badge bg-warning text-dark fw-bold">
                                <i class="bi bi-clock me-1"></i>{{ $offer->duree }}h = {{ $offer->duree }} crédit(s)
                            </span>
                        </div>

                        <h5 class="fw-bold mb-2">
                            <a href="{{ route('offers.show', $offer) }}" class="text-decoration-none text-dark">
                                {{ $offer->titre }}
                            </a>
                        </h5>

                        <div class="mb-2">
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-bookmark-star me-1"></i>{{ $offer->competence }}
                            </span>
                            <span class="badge bg-light text-muted border">
                                {{ ucfirst($offer->niveau) }}
                            </span>
                        </div>

                        <p class="text-muted small flex-grow-1">
                            {{ Str::limit($offer->description, 130) }}
                        </p>

                        <!-- Informations formateur -->
                        <div class="d-flex align-items-center justify-content-between pt-3 border-top mt-auto mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: 700; font-size: 0.85rem;">
                                    {{ strtoupper(substr($offer->user->prenom, 0, 1)) }}
                                </div>
                                <div class="small">
                                    <strong class="text-dark d-block">{{ $offer->user->nom_complet }}</strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">{{ $offer->user->filiere ?? 'Étudiant' }}</span>
                                </div>
                            </div>
                            <div class="text-end small">
                                <span class="text-warning fw-bold">
                                    <i class="bi bi-star-fill"></i> {{ $offer->user->note_moyenne }}
                                </span>
                            </div>
                        </div>

                        <!-- Créneaux et bouton -->
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                <i class="bi bi-calendar-check me-1"></i><strong>{{ $offer->availableSlots->count() }}</strong> créneau(x) dispo
                            </span>
                            <a href="{{ route('offers.show', $offer) }}" class="btn btn-sm btn-brand px-3">
                                Consulter & Réserver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <div class="bg-light p-5 rounded-4 border">
                    <i class="bi bi-search fs-1 text-muted d-block mb-2"></i>
                    <h5 class="fw-bold text-dark">Aucune offre ne correspond à vos critères</h5>
                    <p class="text-muted small">Essayez de modifier vos filtres ou soyez le premier à proposer une séance dans ce domaine !</p>
                    <a href="{{ route('offers.index') }}" class="btn btn-primary btn-sm mt-2">Réinitialiser les filtres</a>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($offers->hasPages())
        <div class="d-flex justify-content-center">
            {{ $offers->links() }}
        </div>
    @endif
</div>
@endsection
