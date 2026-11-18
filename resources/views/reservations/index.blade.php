@extends('layouts.app')

@section('title', 'Gestion des Demandes & Réservations')

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
                    <h3 class="fw-bold text-dark mb-1">Demandes & Réservations</h3>
                    <p class="text-muted small mb-0">Suivez le statut de vos séances en tant que bénéficiaire et intervenant.</p>
                </div>
            </div>

            <!-- Onglets Reçues / Envoyées -->
            <ul class="nav nav-tabs nav-fill mb-4 bg-white rounded-top shadow-sm border" id="reservationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold py-3" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab">
                        <i class="bi bi-inbox-fill text-primary me-2"></i>Demandes reçues sur mes offres ({{ $receivedReservations->total() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold py-3" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">
                        <i class="bi bi-send-fill text-success me-2"></i>Mes demandes de séances envoyées ({{ $sentReservations->total() }})
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="reservationTabsContent">
                <!-- 1. Demandes Reçues (Formateur) -->
                <div class="tab-pane fade show active" id="received" role="tabpanel">
                    <div class="card card-custom">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small text-uppercase">
                                        <tr>
                                            <th class="ps-3">Séance / Offre</th>
                                            <th>Demandeur</th>
                                            <th>Date & Heure</th>
                                            <th>Statut</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($receivedReservations as $res)
                                            <tr>
                                                <td class="ps-3">
                                                    <strong class="text-dark d-block">{{ $res->slot->offer->titre }}</strong>
                                                    <span class="small text-muted">{{ $res->slot->offer->duree }}h &bull; {{ $res->slot->offer->duree }} crédit(s)</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark">{{ $res->beneficiary->nom_complet }}</div>
                                                    <div class="small text-muted">{{ $res->beneficiary->filiere ?? 'Étudiant' }}</div>
                                                </td>
                                                <td class="text-nowrap small">
                                                    <i class="bi bi-calendar-event me-1 text-primary"></i>
                                                    {{ $res->slot->date_debut->format('d/m/Y H:i') }}
                                                </td>
                                                <td>
                                                    @switch($res->status)
                                                        @case('pending')
                                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">En attente de décision</span>
                                                            @break
                                                        @case('accepted')
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Acceptée</span>
                                                            @break
                                                        @case('rejected')
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Refusée</span>
                                                            @break
                                                        @case('cancelled')
                                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Annulée</span>
                                                            @break
                                                        @case('completed')
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Réalisée & Réglée</span>
                                                            @break
                                                        @case('dispute')
                                                            <span class="badge bg-danger">En litige (RG13)</span>
                                                            @break
                                                    @endswitch
                                                </td>
                                                <td class="text-end pe-3">
                                                    @if($res->status === 'pending')
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <form action="{{ route('reservations.accept', $res) }}" method="POST">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                                                    <i class="bi bi-check-lg me-1"></i>Accepter
                                                                </button>
                                                            </form>
                                                            <button type="button" class="btn btn-sm btn-outline-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#refuseModalReceived{{ $res->id }}">
                                                                <i class="bi bi-x-lg me-1"></i>Refuser
                                                            </button>
                                                        </div>

                                                        <!-- Modale refus avec motif obligatoire (RG09) -->
                                                        <div class="modal fade" id="refuseModalReceived{{ $res->id }}" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content text-start">
                                                                    <form action="{{ route('reservations.reject', $res) }}" method="POST">
                                                                        @csrf
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title fw-bold">Refuser la demande</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <label class="form-label small fw-semibold">Motif explicite du refus (RG09)</label>
                                                                            <textarea name="motif" class="form-control" rows="3" placeholder="Ex: Créneau indisponible..." required></textarea>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                                                            <button type="submit" class="btn btn-danger btn-sm fw-bold">Confirmer le refus</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @elseif($res->status === 'accepted')
                                                        @if($res->canBeConfirmed())
                                                            <!-- RG11: Confirmation post-séance -->
                                                            @if(!$res->conf_formateur)
                                                                <form action="{{ route('confirmations.confirm', $res) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                                                        <i class="bi bi-check-circle me-1"></i>Confirmer réalisation
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="badge bg-info-subtle text-info border">Votre confirmation enregistrée</span>
                                                            @endif
                                                        @elseif($res->canBeCancelled())
                                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $res->id }}">
                                                                Annuler la séance
                                                            </button>

                                                            <!-- Modale Annulation formateur -->
                                                            <div class="modal fade" id="cancelModal{{ $res->id }}" tabindex="-1" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content text-start">
                                                                        <form action="{{ route('reservations.cancel', $res) }}" method="POST">
                                                                            @csrf
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title fw-bold">Annuler la réservation acceptée</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <p class="small text-muted">L'annulation libérera immédiatement les crédits bloqués de {{ $res->beneficiary->nom_complet }} (RG10).</p>
                                                                                <label class="form-label small fw-semibold">Motif de l'annulation (optionnel)</label>
                                                                                <input type="text" name="motif" class="form-control" placeholder="Ex: Empêchement de dernière minute">
                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                                                                                <button type="submit" class="btn btn-danger btn-sm fw-bold">Confirmer l'annulation</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-muted small">Séance en cours</span>
                                                        @endif
                                                    @elseif($res->status === 'completed')
                                                        <!-- Invitation à évaluer (RG15) -->
                                                        <a href="{{ route('reviews.create', ['reservation' => $res->id]) }}" class="btn btn-sm btn-outline-warning">
                                                            <i class="bi bi-star me-1"></i>Évaluer
                                                        </a>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted small">
                                                    Aucune demande reçue sur vos offres pour le moment.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if($receivedReservations->hasPages())
                            <div class="card-footer bg-white border-0 py-3">
                                {{ $receivedReservations->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 2. Demandes Envoyées (Bénéficiaire) -->
                <div class="tab-pane fade" id="sent" role="tabpanel">
                    <div class="card card-custom">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small text-uppercase">
                                        <tr>
                                            <th class="ps-3">Séance / Offre</th>
                                            <th>Formateur</th>
                                            <th>Date & Heure</th>
                                            <th>Lieu / Lien (RG19)</th>
                                            <th>Statut</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($sentReservations as $res)
                                            <tr>
                                                <td class="ps-3">
                                                    <a href="{{ route('offers.show', $res->slot->offer) }}" class="fw-bold text-dark text-decoration-none d-block">
                                                        {{ $res->slot->offer->titre }}
                                                    </a>
                                                    <span class="small text-muted">{{ $res->slot->offer->duree }}h &bull; {{ $res->slot->offer->duree }} crédit(s)</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark">{{ $res->slot->offer->user->nom_complet }}</div>
                                                    <div class="small text-muted">{{ $res->slot->offer->user->filiere ?? 'Étudiant' }}</div>
                                                </td>
                                                <td class="text-nowrap small">
                                                    <i class="bi bi-calendar-event me-1 text-primary"></i>
                                                    {{ $res->slot->date_debut->format('d/m/Y H:i') }}
                                                </td>
                                                <td>
                                                    <!-- RG19: Lieu débloqué si acceptée ou réalisée -->
                                                    @if($res->canViewPrivateLocation(Auth::user()))
                                                        <span class="badge bg-light text-dark border" title="{{ $res->slot->lieu_ou_lien }}">
                                                            <i class="bi {{ $res->slot->offer->mode === 'presentiel' ? 'bi-geo-alt' : 'bi-camera-video' }} text-danger me-1"></i>
                                                            {{ $res->slot->lieu_ou_lien }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-light text-muted border">
                                                            <i class="bi bi-lock-fill text-warning me-1"></i>Débloqué dès acceptation (RG19)
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @switch($res->status)
                                                        @case('pending')
                                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">En attente</span>
                                                            @break
                                                        @case('accepted')
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Acceptée (Crédits bloqués)</span>
                                                            @break
                                                        @case('rejected')
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="{{ $res->motif_refus }}">
                                                                Refusée
                                                            </span>
                                                            @break
                                                        @case('cancelled')
                                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Annulée (Crédits libérés)</span>
                                                            @break
                                                        @case('completed')
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Séance Réalisée</span>
                                                            @break
                                                        @case('dispute')
                                                            <span class="badge bg-danger">En litige (RG13)</span>
                                                            @break
                                                    @endswitch
                                                </td>
                                                <td class="text-end pe-3">
                                                    @if($res->status === 'accepted')
                                                        @if($res->canBeConfirmed())
                                                            <!-- RG11: Confirmer réalisation -->
                                                            @if(!$res->conf_beneficiaire)
                                                                <form action="{{ route('confirmations.confirm', $res) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                                                        <i class="bi bi-check-circle me-1"></i>Confirmer réalisation
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="badge bg-info-subtle text-info border">Votre confirmation enregistrée</span>
                                                            @endif
                                                        @elseif($res->canBeCancelled())
                                                            <!-- RG10: Annulation avant début -->
                                                            <form action="{{ route('reservations.cancel', $res) }}" method="POST" class="d-inline" onsubmit="return confirm('Annuler cette réservation libérera immédiatement vos crédits (RG10). Confirmer ?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-x-circle me-1"></i>Annuler (RG10)
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="text-muted small">Séance en cours</span>
                                                        @endif
                                                    @elseif($res->status === 'completed')
                                                        <a href="{{ route('reviews.create', ['reservation' => $res->id]) }}" class="btn btn-sm btn-outline-warning">
                                                            <i class="bi bi-star me-1"></i>Évaluer
                                                        </a>
                                                    @elseif($res->status === 'pending')
                                                        <span class="small text-muted">En attente de l'intervenant</span>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted small">
                                                    Vous n'avez envoyé aucune demande de réservation. Explorez le catalogue pour vous former !
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if($sentReservations->hasPages())
                            <div class="card-footer bg-white border-0 py-3">
                                {{ $sentReservations->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
