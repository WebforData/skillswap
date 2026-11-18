@extends('layouts.app')

@section('title', 'Arbitrage des Litiges')

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
                    <h3 class="fw-bold text-dark mb-1">Centre d'Arbitrage des Litiges (RG13 & RG14)</h3>
                    <p class="text-muted small mb-0">Examinez les déclarations, motivez votre décision et effectuez soit le transfert intégral, soit la restitution intégrale.</p>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-hammer text-danger me-2"></i>Dossiers d'arbitrage ({{ $disputes->total() }})</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Séance & Offre</th>
                                    <th>Parties prenantes (RG19)</th>
                                    <th>Motif du litige</th>
                                    <th>Statut</th>
                                    <th class="text-end pe-3">Action d'arbitrage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($disputes as $disp)
                                    @php
                                        $reservation = $disp->reservation;
                                        $formateur = $reservation->slot->offer->user;
                                        $beneficiaire = $reservation->beneficiary;
                                        $cout = $reservation->slot->offer->duree;
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <strong class="text-dark d-block">{{ $reservation->slot->offer->titre }}</strong>
                                            <span class="small text-muted">{{ $reservation->slot->date_debut->format('d/m/Y H:i') }} &bull; Enjeu : {{ $cout }} crédit(s)</span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <i class="bi bi-person-fill text-primary"></i> <strong>Formateur :</strong> {{ $formateur->nom_complet }}
                                            </div>
                                            <div class="small">
                                                <i class="bi bi-person-fill text-success"></i> <strong>Bénéficiaire :</strong> {{ $beneficiaire->nom_complet }}
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Ouvert par : {{ $disp->openedBy->nom_complet }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $disp->motif }}</span>
                                            <div class="small text-secondary mt-1 text-truncate" style="max-width: 250px;">{{ $disp->description }}</div>
                                        </td>
                                        <td>
                                            @if($disp->isResolved())
                                                @if($disp->resolution === 'transfert_formateur')
                                                    <span class="badge bg-success">Tranché : Transfert à l'intervenant</span>
                                                @else
                                                    <span class="badge bg-info text-dark">Tranché : Restitution au bénéficiaire</span>
                                                @endif
                                                <div class="small text-muted mt-1" style="font-size: 0.75rem;">Décision définitive (RG14)</div>
                                            @else
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-hourglass-split me-1"></i>En attente d'arbitrage (Crédits bloqués - RG13)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(!$disp->isResolved())
                                                <button type="button" class="btn btn-sm btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#arbitrageModal{{ $disp->id }}">
                                                    <i class="bi bi-hammer me-1"></i>Trancher le litige (RG13)
                                                </button>

                                                <!-- Modale Arbitrage Administrateur -->
                                                <div class="modal fade" id="arbitrageModal{{ $disp->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg text-start">
                                                        <div class="modal-content">
                                                            <form action="{{ route('admin.disputes.resolve', $disp) }}" method="POST">
                                                                @csrf
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title fw-bold">
                                                                        <i class="bi bi-shield-shaded text-danger me-2"></i>Arbitrage du dossier #{{ $disp->id }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="alert alert-light border mb-3">
                                                                        <strong>Détails du différend :</strong><br>
                                                                        <span class="badge bg-secondary mb-1">Motif : {{ $disp->motif }}</span>
                                                                        <p class="small text-dark mb-0">{{ $disp->description }}</p>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Décision administrative binaire (RG13 & RG14)</label>
                                                                        <div class="form-check p-3 border rounded mb-2 bg-light">
                                                                            <input class="form-check-input" type="radio" name="resolution" id="res_formateur_{{ $disp->id }}" value="transfert_formateur" required>
                                                                            <label class="form-check-label fw-semibold" for="res_formateur_{{ $disp->id }}">
                                                                                <strong class="text-success">Transfert intégral au formateur ({{ $formateur->nom_complet }})</strong>
                                                                                <div class="small text-muted fw-normal">Valide la prestation : débite {{ $cout }}h bloquée(s) chez {{ $beneficiaire->nom_complet }} et crédite {{ $cout }}h chez {{ $formateur->nom_complet }}. Clôture la séance comme réalisée.</div>
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-check p-3 border rounded bg-light">
                                                                            <input class="form-check-input" type="radio" name="resolution" id="res_beneficiaire_{{ $disp->id }}" value="restitution_beneficiaire" required>
                                                                            <label class="form-check-label fw-semibold" for="res_beneficiaire_{{ $disp->id }}">
                                                                                <strong class="text-primary">Restitution intégrale au bénéficiaire ({{ $beneficiaire->nom_complet }})</strong>
                                                                                <div class="small text-muted fw-normal">Annule la séance : débloque les {{ $cout }}h pour les restituer immédiatement sur le solde disponible de {{ $beneficiaire->nom_complet }}.</div>
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-text text-danger small mt-1">
                                                                            <i class="bi bi-exclamation-triangle-fill"></i> Conformément à la règle RG13, les règlements partiels sont strictement exclus.
                                                                        </div>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label for="decision_admin_{{ $disp->id }}" class="form-label small fw-semibold">Motivation obligatoire de la décision (RG13)</label>
                                                                        <textarea name="decision_admin" id="decision_admin_{{ $disp->id }}" rows="4" class="form-control" placeholder="Indiquez les éléments probants ayant guidé votre arbitrage..." required></textarea>
                                                                        <div class="form-text small">Cette motivation sera notifiée aux deux étudiants et inscrite définitivement en BDD (RG14).</div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                                                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                                                                        Confirmer le règlement définitif (RG14)
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewDecisionModal{{ $disp->id }}">
                                                    Consulter décision
                                                </button>

                                                <!-- Modale Consultation décision -->
                                                <div class="modal fade" id="viewDecisionModal{{ $disp->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog text-start">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold">Décision arbitrale clôturée</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-2"><strong>Issue :</strong> {{ $disp->resolution }}</div>
                                                                <div class="mb-2"><strong>Date de clôture :</strong> {{ $disp->resolved_at?->format('d/m/Y H:i') }}</div>
                                                                <div class="p-3 bg-light rounded border">
                                                                    <strong>Motivation :</strong><br>
                                                                    {{ $disp->decision_admin }}
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">Aucun dossier de litige dans la base de données.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($disputes->hasPages())
                    <div class="card-footer bg-white border-0 py-3">
                        {{ $disputes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
