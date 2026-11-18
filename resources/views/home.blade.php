@extends('layouts.app')

@section('title', 'Accueil')

@section('content')
<!-- Hero Section Universitaire -->
<section class="py-5" style="background: linear-gradient(135deg, #0d2847 0%, #1a56db 100%); color: #ffffff;">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3">
                    <i class="bi bi-clock-history me-1"></i> Banque de Temps Universitaire
                </span>
                <h1 class="display-4 fw-bold mb-3">Partagez vos compétences, gagnez du temps d'apprentissage.</h1>
                <p class="lead text-light opacity-90 mb-4">
                    <strong>1 heure animée = 1 crédit gagné = 1 heure de cours reçue.</strong><br>
                    SkillSwap Campus permet aux étudiants d'échanger leurs savoirs en programmation, langues, méthodologie ou design, <strong>sans aucun paiement en argent</strong>.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('offers.index') }}" class="btn btn-warning text-dark fw-bold px-4 py-2">
                        <i class="bi bi-search me-1"></i> Explorer les offres
                    </a>
                    @guest
                        <a href="{{ route('register') }}" class="btn btn-outline-light fw-bold px-4 py-2">
                            <i class="bi bi-gift-fill me-1"></i> S'inscrire (+2 crédits gratuits)
                        </a>
                    @else
                        <a href="{{ route('offers.create') }}" class="btn btn-outline-light fw-bold px-4 py-2">
                            <i class="bi bi-plus-circle me-1"></i> Proposer une séance
                        </a>
                    @endguest
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card bg-white text-dark rounded-4 shadow-lg p-4 border-0">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat text-primary fs-4"></i> Comment ça marche ?
                    </h5>
                    <div class="d-flex gap-3 mb-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; font-weight: 700;">1</div>
                        <div>
                            <strong class="d-block">Inscription & 2 crédits offerts</strong>
                            <span class="text-muted small">Chaque nouvel étudiant reçoit immédiatement 2 heures de formation gratuites (RG01).</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; font-weight: 700;">2</div>
                        <div>
                            <strong class="d-block">Animez une séance d'une compétence</strong>
                            <span class="text-muted small">Python, Anglais, SQL, Git... Choisissez une durée de 1h ou 2h (RG02).</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; font-weight: 700;">3</div>
                        <div>
                            <strong class="d-block">Transfert automatique & avis mutuels</strong>
                            <span class="text-muted small">Après validation de la séance, les crédits sont transférés et vous notez votre pair (RG11, RG15).</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistiques Clés -->
<section class="py-4 bg-white border-bottom">
    <div class="container">
        <div class="row text-center g-3">
            <div class="col-6 col-md-3">
                <div class="fs-2 fw-bold text-primary">{{ $stats['users_count'] }}</div>
                <div class="text-muted small">Étudiants actifs</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-2 fw-bold text-primary">{{ $stats['offers_count'] }}</div>
                <div class="text-muted small">Offres de compétences</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-2 fw-bold text-success">{{ $stats['completed_count'] }}</div>
                <div class="text-muted small">Séances réalisées</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-2 fw-bold text-warning">{{ $stats['credits_transferred'] }} h</div>
                <div class="text-muted small">Crédits de temps échangés</div>
            </div>
        </div>
    </div>
</section>

<!-- Catégories -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark">Explorez par Catégorie de Compétence</h2>
            <p class="text-muted">Des compétences variées adaptées aux besoins du cursus universitaire.</p>
        </div>

        <div class="row g-3">
            @foreach($categories as $cat)
                <div class="col-md-4 col-lg">
                    <a href="{{ route('offers.index', ['category_id' => $cat->id]) }}" class="text-decoration-none">
                        <div class="card card-custom text-center p-3 h-100">
                            <div class="bg-primary-subtle text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                                <i class="bi {{ $cat->icon ?? 'bi-tag' }} fs-4"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">{{ $cat->nom }}</h6>
                            <span class="badge bg-light text-muted border">{{ $cat->offers_count }} offre(s)</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Offres Récentes -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Dernières Offres Publiées</h3>
                <p class="text-muted small mb-0">Réservez dès maintenant une séance avec un étudiant qualifié.</p>
            </div>
            <a href="{{ route('offers.index') }}" class="btn btn-outline-primary btn-sm fw-semibold">
                Voir toutes les offres <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="row g-4">
            @forelse($recentOffers as $offer)
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom h-100 d-flex flex-column">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ $offer->category->nom }}
                                </span>
                                <span class="badge bg-warning text-dark fw-bold">
                                    <i class="bi bi-clock me-1"></i>{{ $offer->duree }} heure(s) ({{ $offer->duree }} crédit)
                                </span>
                            </div>

                            <h5 class="fw-bold text-dark mb-2">
                                <a href="{{ route('offers.show', $offer) }}" class="text-decoration-none text-dark">
                                    {{ $offer->titre }}
                                </a>
                            </h5>

                            <p class="text-muted small flex-grow-1">
                                {{ Str::limit($offer->description, 110) }}
                            </p>

                            <div class="d-flex align-items-center gap-2 mb-3 pt-2 border-top">
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.8rem; font-weight: 700;">
                                    {{ strtoupper(substr($offer->user->prenom, 0, 1)) }}
                                </div>
                                <div class="small">
                                    <strong class="text-dark">{{ $offer->user->nom_complet }}</strong>
                                    <div class="text-muted" style="font-size: 0.75rem;">{{ $offer->user->filiere ?? 'Étudiant' }}</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge {{ $offer->mode === 'presentiel' ? 'bg-info-subtle text-info' : 'bg-success-subtle text-success' }}">
                                    <i class="bi {{ $offer->mode === 'presentiel' ? 'bi-geo-alt' : 'bi-camera-video' }} me-1"></i>{{ ucfirst($offer->mode) }}
                                </span>
                                <a href="{{ route('offers.show', $offer) }}" class="btn btn-sm btn-brand">
                                    Voir créneaux ({{ $offer->availableSlots->count() }})
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5 text-muted">
                    <p>Aucune offre pour le moment.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
