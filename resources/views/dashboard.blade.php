@extends('layouts.app')

@section('title', 'Tableau de bord Étudiant')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 col-xl-2 d-none d-lg-block">
            @include('layouts.sidebar')
        </div>

        <!-- Contenu principal -->
        <div class="col-lg-9 col-xl-10">
            <!-- Header du Dashboard -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold text-dark mb-1">Bonjour, {{ $user->prenom }} 👋</h3>
                    <p class="text-muted small mb-0">{{ $user->filiere ?? 'Étudiant' }} &bull; {{ $user->niveau_etudes ?? '' }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('offers.create') }}" class="btn btn-brand btn-sm px-3 shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> Proposer une offre
                    </a>
                    <a href="{{ route('offers.index') }}" class="btn btn-outline-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Réserver une séance
                    </a>
                </div>
            </div>

            <!-- Cartes statistiques KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card card-custom p-3 border-start border-4 border-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Solde Disponible</div>
                                <div class="fs-3 fw-bold text-success">{{ $user->solde_disponible }} h</div>
                                <a href="{{ route('wallet.index') }}" class="small text-decoration-none text-muted">Voir portefeuille &rarr;</a>
                            </div>
                            <div class="bg-success-subtle text-success rounded-circle p-3">
                                <i class="bi bi-clock-fill fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card card-custom p-3 border-start border-4 border-warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Crédits Bloqués</div>
                                <div class="fs-3 fw-bold text-warning">{{ $user->solde_bloque }} h</div>
                                <span class="small text-muted">{{ $upcomingSessions->count() }} séance(s) en cours</span>
                            </div>
                            <div class="bg-warning-subtle text-warning rounded-circle p-3">
                                <i class="bi bi-lock-fill fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card card-custom p-3 border-start border-4 border-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Mes Offres Actives</div>
                                <div class="fs-3 fw-bold text-primary">{{ $myOffersCount }}</div>
                                <a href="{{ route('offers.my') }}" class="small text-decoration-none text-muted">Gérer mes offres &rarr;</a>
                            </div>
                            <div class="bg-primary-subtle text-primary rounded-circle p-3">
                                <i class="bi bi-journal-check fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card card-custom p-3 border-start border-4 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Note Moyenne</div>
                                <div class="fs-3 fw-bold text-dark">
                                    <i class="bi bi-star-fill text-warning fs-5"></i> {{ $user->note_moyenne }} <span class="fs-6 text-muted">/ 5</span>
                                </div>
                                <span class="small text-muted">Sur {{ $user->nombre_avis }} évaluation(s)</span>
                            </div>
                            <div class="bg-info-subtle text-info rounded-circle p-3">
                                <i class="bi bi-award-fill fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerte : Séances terminées à confirmer (RG11) -->
            @if($pendingConfirmations->isNotEmpty())
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-bell-fill fs-4 text-warning"></i>
                        <div>
                            <strong>Séance(s) terminée(s) à confirmer !</strong>
                            <div class="small">Vous avez {{ $pendingConfirmations->count() }} séance(s) achevée(s) nécessitant votre confirmation pour transférer les crédits (RG11).</div>
                        </div>
                    </div>
                    <a href="{{ route('reservations.index') }}" class="btn btn-warning btn-sm fw-bold">
                        Confirmer maintenant
                    </a>
                </div>
            @endif

            <div class="row g-4 mb-4">
                <!-- Colonne Gauche : Demandes reçues en attente -->
                <div class="col-xl-7">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><i class="bi bi-inbox me-2 text-primary"></i>Demandes reçues à valider</span>
                            <span class="badge bg-primary">{{ $pendingDemandes->count() }}</span>
                        </div>
                        <div class="card-body p-0">
                            @forelse($pendingDemandes as $demande)
                                <div class="p-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $demande->slot->offer->titre }}</div>
                                        <div class="small text-muted mb-1">
                                            Demandeur : <strong>{{ $demande->beneficiary->nom_complet }}</strong> ({{ $demande->beneficiary->filiere ?? 'Étudiant' }})
                                        </div>
                                        <div class="small text-primary">
                                            <i class="bi bi-calendar-event me-1"></i>{{ $demande->slot->date_debut->format('d/m/Y à H:i') }}
                                            ({{ $demande->slot->offer->duree }}h &bull; {{ $demande->slot->offer->duree }} crédit(s))
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <!-- Bouton Accepter (RG06, RG07, RG08) -->
                                        <form action="{{ route('reservations.accept', $demande) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm fw-semibold">
                                                <i class="bi bi-check-lg me-1"></i>Accepter
                                            </button>
                                        </form>

                                        <!-- Bouton Refuser avec Modale de Motif obligatoire (RG09) -->
                                        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#refuseModal{{ $demande->id }}">
                                            <i class="bi bi-x-lg me-1"></i>Refuser
                                        </button>
                                    </div>
                                </div>

                                <!-- Modale Refus de séance -->
                                <div class="modal fade" id="refuseModal{{ $demande->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('reservations.reject', $demande) }}" method="POST">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Refuser la demande de séance</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small text-muted mb-3">Veuillez indiquer un motif explicite pour informer {{ $demande->beneficiary->nom_complet }} (RG09).</p>
                                                    <div class="mb-3">
                                                        <label for="motif_{{ $demande->id }}" class="form-label small fw-semibold">Motif du refus</label>
                                                        <textarea name="motif" id="motif_{{ $demande->id }}" class="form-control" rows="3" placeholder="Ex: Indisponibilité imprévue, sujet déjà traité..." required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-danger btn-sm fw-bold">Confirmer le refus</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-muted small">
                                    <i class="bi bi-check-circle fs-3 text-success d-block mb-1"></i>
                                    Aucune demande en attente. Toutes vos offres sont à jour !
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Colonne Droite : Prochaines séances acceptées (Planning rapide) -->
                <div class="col-xl-5">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><i class="bi bi-calendar-check me-2 text-success"></i>Prochaines séances acceptées</span>
                            <a href="{{ route('planning') }}" class="small text-decoration-none">Voir planning complet</a>
                        </div>
                        <div class="card-body p-0">
                            @forelse($upcomingSessions->take(4) as $session)
                                @php
                                    $isBeneficiary = ($user->id === $session->beneficiary_id);
                                    $autreParticipant = $isBeneficiary ? $session->slot->offer->user : $session->beneficiary;
                                @endphp
                                <div class="p-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="badge {{ $isBeneficiary ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }}">
                                            {{ $isBeneficiary ? 'Apprenant' : 'Formateur' }}
                                        </span>
                                        <span class="small text-muted">
                                            <i class="bi bi-clock me-1"></i>{{ $session->slot->offer->duree }}h
                                        </span>
                                    </div>
                                    <div class="fw-bold text-dark">{{ $session->slot->offer->titre }}</div>
                                    <div class="small text-muted mb-2">
                                        Avec : <strong>{{ $autreParticipant->nom_complet }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small">
                                        <span class="text-success fw-semibold">
                                            <i class="bi bi-calendar-event me-1"></i>{{ $session->slot->date_debut->format('d/m/Y H:i') }}
                                        </span>
                                        <!-- RG19: Lieu ou lien privé visible uniquement ici car séance acceptée -->
                                        <span class="badge bg-light text-dark border text-truncate" style="max-width: 160px;" title="{{ $session->slot->lieu_ou_lien }}">
                                            <i class="bi {{ $session->slot->offer->mode === 'presentiel' ? 'bi-geo-alt' : 'bi-camera-video' }} me-1"></i>
                                            {{ $session->slot->lieu_ou_lien }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-muted small">
                                    <i class="bi bi-calendar-x fs-3 text-secondary d-block mb-1"></i>
                                    Aucune séance acceptée à venir. Explorez le catalogue pour réserver !
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suggestions de séances personnalisées selon compétences recherchées -->
            <div class="card card-custom mb-4">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold"><i class="bi bi-stars me-2 text-warning"></i>Séances recommandées pour vous</span>
                        <span class="text-muted small ms-2 d-none d-sm-inline">(Basées sur vos compétences recherchées)</span>
                    </div>
                    <a href="{{ route('profile.show') }}" class="small text-decoration-none">Ajuster mes compétences recherchées</a>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        @forelse($suggestions as $sug)
                            <div class="col-md-6 col-xl-3">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                            {{ $sug->competence }}
                                        </span>
                                        <span class="badge bg-warning text-dark fw-bold small">
                                            {{ $sug->duree }}h
                                        </span>
                                    </div>
                                    <h6 class="fw-bold mb-1">
                                        <a href="{{ route('offers.show', $sug) }}" class="text-decoration-none text-dark text-truncate d-block">
                                            {{ $sug->titre }}
                                        </a>
                                    </h6>
                                    <p class="small text-muted mb-2 text-truncate">{{ $sug->description }}</p>
                                    <div class="small text-muted mb-3">Par <strong>{{ $sug->user->nom_complet }}</strong></div>
                                    <a href="{{ route('offers.show', $sug) }}" class="btn btn-outline-primary btn-sm w-100 mt-auto">
                                        Voir les créneaux ({{ $sug->availableSlots->count() }})
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-4 text-muted small">
                                Renseignez des compétences recherchées dans votre <a href="{{ route('profile.show') }}">profil</a> pour obtenir des suggestions personnalisées.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
