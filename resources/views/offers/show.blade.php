@extends('layouts.app')

@section('title', $offer->titre)

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('offers.index') }}" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i> Retour au catalogue des offres
        </a>
    </div>

    <div class="row g-4">
        <!-- Colonne Gauche : Détails de l'offre -->
        <div class="col-lg-8">
            <div class="card card-custom mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
                            {{ $offer->category->nom }}
                        </span>
                        <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold">
                            <i class="bi bi-clock me-1"></i>{{ $offer->duree }} heure(s) = {{ $offer->duree }} crédit(s) (RG02)
                        </span>
                        <span class="badge {{ $offer->mode === 'presentiel' ? 'bg-info-subtle text-info' : 'bg-success-subtle text-success' }} px-3 py-2 fs-6">
                            <i class="bi {{ $offer->mode === 'presentiel' ? 'bi-geo-alt' : 'bi-camera-video' }} me-1"></i>{{ ucfirst($offer->mode) }}
                        </span>
                        <span class="badge bg-light text-muted border px-3 py-2 fs-6">
                            Niveau : {{ ucfirst($offer->niveau) }}
                        </span>
                    </div>

                    <h2 class="fw-bold text-dark mb-3">{{ $offer->titre }}</h2>

                    <div class="d-flex align-items-center gap-2 mb-4 text-muted small">
                        <span><i class="bi bi-calendar3 me-1"></i>Publiée le {{ $offer->created_at->format('d/m/Y') }}</span>
                        <span>&bull;</span>
                        <span><i class="bi bi-bookmark-fill text-primary me-1"></i>Compétence : <strong>{{ $offer->competence }}</strong></span>
                    </div>

                    <hr class="my-4">

                    <h5 class="fw-bold text-dark mb-3">Description de la séance</h5>
                    <div class="text-secondary lh-lg mb-4" style="white-space: pre-line;">
                        {{ $offer->description }}
                    </div>

                    <hr class="my-4">

                    <!-- Créneaux disponibles -->
                    <h5 class="fw-bold text-dark mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-calendar-check me-2 text-primary"></i>Créneaux disponibles pour réservation</span>
                        <span class="badge bg-light text-dark border fs-6">{{ $offer->availableSlots->count() }} créneau(x)</span>
                    </h5>

                    <div class="list-group mb-3">
                        @forelse($offer->availableSlots as $slot)
                            <div class="list-group-item p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div>
                                    <div class="fw-bold text-dark fs-6">
                                        <i class="bi bi-calendar-event text-primary me-2"></i>
                                        {{ $slot->date_debut->isoFormat('dddd D MMMM YYYY [à] HH:mm') }}
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-clock me-1"></i>Durée : {{ $offer->duree }}h (fin prévue à {{ $slot->date_fin->format('H:i') }})
                                    </div>
                                    <div class="small mt-1">
                                        <!-- RG19: Confidentialité du lieu / lien -->
                                        @if(Auth::check() && (Auth::id() === $offer->user_id || Auth::user()->isAdmin()))
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ $slot->lieu_ou_lien }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border" title="Le lien ou lieu exact sera dévoilé aux participants dès l'acceptation de la séance (RG19)">
                                                <i class="bi bi-lock-fill text-warning me-1"></i>Lieu / Lien privé (Accessible après acceptation - RG19)
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    @guest
                                        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm fw-semibold">
                                            Se connecter pour réserver
                                        </a>
                                    @else
                                        @if(Auth::id() === $offer->user_id)
                                            <span class="badge bg-secondary p-2">Votre propre créneau</span>
                                        @else
                                            @php
                                                $alreadyRequested = \App\Models\Reservation::where('slot_id', $slot->id)
                                                    ->where('beneficiary_id', Auth::id())
                                                    ->whereIn('status', ['pending', 'accepted'])
                                                    ->exists();
                                            @endphp

                                            @if($alreadyRequested)
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="bi bi-check2 me-1"></i>Demande déjà envoyée
                                                </button>
                                            @elseif(Auth::user()->solde_disponible < $offer->duree)
                                                <button class="btn btn-outline-danger btn-sm" disabled title="Votre solde disponible ({{ Auth::user()->solde_disponible }}h) est insuffisant ({{ $offer->duree }}h requise(s)) (RG05)">
                                                    <i class="bi bi-exclamation-circle me-1"></i>Crédits insuffisants
                                                </button>
                                            @else
                                                <form action="{{ route('reservations.store', $slot) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-brand btn-sm fw-bold px-3">
                                                        <i class="bi bi-bookmark-plus me-1"></i>Réserver ({{ $offer->duree }} crédit)
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    @endguest
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted bg-light rounded-3">
                                <i class="bi bi-calendar-x fs-3 d-block text-secondary mb-1"></i>
                                Aucun créneau futur disponible pour le moment.
                            </div>
                        @endforelse
                    </div>

                    <!-- Espace d'ajout de créneaux pour le formateur propriétaire -->
                    @if(Auth::check() && Auth::id() === $offer->user_id)
                        <div class="card bg-light border p-3 mt-4">
                            <h6 class="fw-bold text-dark mb-2">
                                <i class="bi bi-plus-circle-fill text-primary me-1"></i> Ajouter un créneau à votre offre
                            </h6>
                            <form action="{{ route('slots.store', $offer) }}" method="POST">
                                @csrf
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label for="date_debut" class="form-label small fw-semibold">Date et heure de début</label>
                                        <input type="datetime-local" name="date_debut" id="date_debut" class="form-control form-control-sm" required min="{{ now()->addMinutes(30)->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="lieu_ou_lien" class="form-label small fw-semibold">Lieu physique ou Lien visio privé (RG19)</label>
                                        <input type="text" name="lieu_ou_lien" id="lieu_ou_lien" class="form-control form-control-sm" placeholder="Ex: Salle B12 ou meet.google.com/xyz" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                            Ajouter
                                        </button>
                                    </div>
                                </div>
                                <div class="form-text small text-muted mt-1">La date de fin sera automatiquement calculée sur {{ $offer->duree }} heure(s) (RG02).</div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Colonne Droite : Formateur & Règles de banque de temps -->
        <div class="col-lg-4">
            <!-- Profil Formateur -->
            <div class="card card-custom mb-4">
                <div class="card-header card-header-custom">
                    <i class="bi bi-person-badge me-2 text-primary"></i>À propos de l'intervenant
                </div>
                <div class="card-body p-4 text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 64px; height: 64px; font-weight: 700; font-size: 1.6rem;">
                        {{ strtoupper(substr($offer->user->prenom, 0, 1)) }}
                    </div>
                    <h5 class="fw-bold mb-1">{{ $offer->user->nom_complet }}</h5>
                    <p class="text-muted small mb-2">{{ $offer->user->filiere ?? 'Étudiant' }} &bull; {{ $offer->user->niveau_etudes ?? '' }}</p>

                    <div class="d-inline-flex align-items-center gap-1 bg-light border px-3 py-1 rounded-pill mb-3">
                        <i class="bi bi-star-fill text-warning"></i>
                        <span class="fw-bold">{{ $offer->user->note_moyenne }} / 5</span>
                        <span class="text-muted small">({{ $offer->user->nombre_avis }} avis)</span>
                    </div>

                    @if($offer->user->skillsProposees->isNotEmpty())
                        <div class="text-start border-top pt-3">
                            <span class="small fw-semibold text-muted d-block mb-1">Autres compétences proposées :</span>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($offer->user->skillsProposees as $sk)
                                    <span class="badge bg-light text-dark border small">{{ $sk->competence }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Rappel des règles du système -->
            <div class="card card-custom bg-light border-0">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-2 small d-flex align-items-center gap-1">
                        <i class="bi bi-info-circle-fill text-primary"></i> Règles de la banque de temps :
                    </h6>
                    <ul class="small text-muted ps-3 mb-0">
                        <li class="mb-1"><strong>RG02 :</strong> La durée est de {{ $offer->duree }}h = coût de {{ $offer->duree }} crédit(s).</li>
                        <li class="mb-1"><strong>RG05 / RG06 :</strong> Les crédits ne sont bloqués qu'après acceptation par le formateur.</li>
                        <li class="mb-1"><strong>RG10 :</strong> Annulation possible jusqu'à la dernière minute avant le début.</li>
                        <li><strong>RG11 :</strong> Transfert des crédits effectué seulement après double confirmation des deux étudiants.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
