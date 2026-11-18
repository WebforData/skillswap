@extends('layouts.app')

@section('title', 'Mon Planning Personnel')

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
                    <h3 class="fw-bold text-dark mb-1">Mon Planning Personnel</h3>
                    <p class="text-muted small mb-0">Visualisez vos séances confirmées, accédez aux liens de réunion privés et suivez leur statut.</p>
                </div>
            </div>

            <!-- 1. Séances À Venir -->
            <div class="card card-custom mb-4">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-success">
                        <i class="bi bi-calendar-event me-2"></i>Séances confirmées à venir ({{ $upcoming->count() }})
                    </span>
                </div>
                <div class="card-body p-4">
                    @forelse($upcoming as $item)
                        @php
                            $isBeneficiary = ($user->id === $item->beneficiary_id);
                            $autreParticipant = $isBeneficiary ? $item->slot->offer->user : $item->beneficiary;
                        @endphp
                        <div class="card border mb-3 shadow-sm">
                            <div class="card-body p-3">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-3">
                                        <div class="fw-bold fs-6 text-primary">
                                            <i class="bi bi-clock me-1"></i>{{ $item->slot->date_debut->format('d/m/Y') }}
                                        </div>
                                        <div class="text-dark fw-semibold">
                                            {{ $item->slot->date_debut->format('H:i') }} &rarr; {{ $item->slot->date_fin->format('H:i') }}
                                            ({{ $item->slot->offer->duree }}h)
                                        </div>
                                        <span class="badge {{ $isBeneficiary ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }} mt-1">
                                            Vous êtes : {{ $isBeneficiary ? 'Bénéficiaire' : 'Formateur' }}
                                        </span>
                                    </div>

                                    <div class="col-md-5">
                                        <h6 class="fw-bold mb-1 text-dark">{{ $item->slot->offer->titre }}</h6>
                                        <div class="small text-muted mb-2">
                                            Avec : <strong>{{ $autreParticipant->nom_complet }}</strong> ({{ $autreParticipant->filiere ?? 'Étudiant' }})
                                        </div>
                                        <!-- RG19: Lieu ou lien privé -->
                                        <div class="p-2 rounded bg-light border d-inline-block small">
                                            <i class="bi {{ $item->slot->offer->mode === 'presentiel' ? 'bi-geo-alt-fill text-danger' : 'bi-camera-video-fill text-primary' }} me-1"></i>
                                            @if(str_starts_with($item->slot->lieu_ou_lien, 'http'))
                                                <a href="{{ $item->slot->lieu_ou_lien }}" target="_blank" class="fw-bold text-decoration-none">
                                                    Rejoindre la visio <i class="bi bi-box-arrow-up-right small"></i>
                                                </a>
                                            @else
                                                <strong>Lieu :</strong> {{ $item->slot->lieu_ou_lien }}
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-md-4 text-md-end">
                                        @if($item->canBeCancelled())
                                            <form action="{{ route('reservations.cancel', $item) }}" method="POST" onsubmit="return confirm('Annuler cette séance libérera les crédits bloqués (RG10). Confirmer ?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-x-circle me-1"></i>Annuler la séance (RG10)
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge bg-light text-muted border">Imminente ou en cours</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-calendar2-check fs-2 text-secondary d-block mb-2"></i>
                            Aucune séance à venir dans votre planning.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- 2. Séances Passées (En attente de confirmation post-séance) -->
            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-clock-history me-2 text-warning"></i>Séances passées à valider ({{ $past->count() }})
                    </span>
                    <span class="text-muted small">RG11 : Double confirmation requise pour libérer les crédits</span>
                </div>
                <div class="card-body p-4">
                    @forelse($past as $item)
                        @php
                            $isBeneficiary = ($user->id === $item->beneficiary_id);
                            $autreParticipant = $isBeneficiary ? $item->slot->offer->user : $item->beneficiary;
                            $monStatutConf = $isBeneficiary ? $item->conf_beneficiaire : $item->conf_formateur;
                            $autreStatutConf = $isBeneficiary ? $item->conf_formateur : $item->conf_beneficiaire;
                        @endphp
                        <div class="card border mb-3">
                            <div class="card-body p-3">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-3">
                                        <div class="fw-bold fs-6 text-dark">
                                            {{ $item->slot->date_debut->format('d/m/Y') }}
                                        </div>
                                        <div class="small text-muted">
                                            {{ $item->slot->date_debut->format('H:i') }} &rarr; {{ $item->slot->date_fin->format('H:i') }}
                                        </div>
                                        <span class="badge bg-secondary-subtle text-secondary mt-1">Séance terminée</span>
                                    </div>

                                    <div class="col-md-5">
                                        <h6 class="fw-bold mb-1 text-dark">{{ $item->slot->offer->titre }}</h6>
                                        <div class="small text-muted mb-2">Avec : {{ $autreParticipant->nom_complet }}</div>
                                        <div class="small">
                                            <div>
                                                Votre confirmation :
                                                @if($monStatutConf)
                                                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Validée</span>
                                                @else
                                                    <span class="text-warning fw-bold"><i class="bi bi-hourglass-split"></i> En attente</span>
                                                @endif
                                            </div>
                                            <div>
                                                Confirmation du pair :
                                                @if($autreStatutConf)
                                                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Validée</span>
                                                @else
                                                    <span class="text-muted"><i class="bi bi-hourglass"></i> Pas encore confirmée (RG12)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 text-md-end d-flex flex-column gap-2 align-items-md-end">
                                        @if(!$monStatutConf)
                                            <form action="{{ route('confirmations.confirm', $item) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm fw-bold">
                                                    <i class="bi bi-check2-circle me-1"></i>Confirmer réalisation (RG11)
                                                </button>
                                            </form>
                                        @endif

                                        <!-- Signaler un problème / litige (RG10, RG12, RG13) -->
                                        <a href="{{ route('disputes.create', ['reservation' => $item->id]) }}" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Signaler un problème
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">
                            Aucune séance passée en attente de traitement.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
