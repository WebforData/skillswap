@extends('layouts.app')

@section('title', 'Portefeuille & Historique des Crédits')

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
                    <h3 class="fw-bold text-dark mb-1">Portefeuille de Crédits de Temps</h3>
                    <p class="text-muted small mb-0">Suivez vos crédits disponibles, vos crédits bloqués pour les séances futures et l'historique complet (RG16).</p>
                </div>
            </div>

            <!-- Cartes statistiques de solde -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card card-custom border-start border-4 border-success p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Crédits Disponibles</div>
                                <div class="fs-2 fw-bold text-success">{{ $user->solde_disponible }} <span class="fs-5 text-muted">heure(s)</span></div>
                                <div class="small text-muted">Prêt(e) pour réserver de nouvelles séances</div>
                            </div>
                            <div class="bg-success-subtle text-success rounded-circle p-3">
                                <i class="bi bi-clock-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-custom border-start border-4 border-warning p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Crédits Bloqués</div>
                                <div class="fs-2 fw-bold text-warning">{{ $user->solde_bloque }} <span class="fs-5 text-muted">heure(s)</span></div>
                                <div class="small text-muted">Réservations acceptées en cours (RG06)</div>
                            </div>
                            <div class="bg-warning-subtle text-warning rounded-circle p-3">
                                <i class="bi bi-lock-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-custom border-start border-4 border-primary p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Solde Total</div>
                                <div class="fs-2 fw-bold text-primary">{{ $user->solde_total }} <span class="fs-5 text-muted">heure(s)</span></div>
                                <div class="small text-muted">Patrimoine total dans la banque de temps</div>
                            </div>
                            <div class="bg-primary-subtle text-primary rounded-circle p-3">
                                <i class="bi bi-bank2 fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau historique des transactions (RG16) -->
            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Historique des mouvements de crédits</span>
                    <span class="badge bg-light text-dark border">{{ $transactions->total() }} opération(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3">Date</th>
                                    <th>Type d'opération</th>
                                    <th>Séance concernée</th>
                                    <th>Description</th>
                                    <th class="text-end pe-3">Variation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $tx)
                                    <tr>
                                        <td class="ps-3 text-nowrap small text-muted">
                                            {{ $tx->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td>
                                            @switch($tx->type)
                                                @case('bienvenue')
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                        <i class="bi bi-gift me-1"></i>Bienvenue
                                                    </span>
                                                    @break
                                                @case('blocage')
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                        <i class="bi bi-lock me-1"></i>Blocage réservation
                                                    </span>
                                                    @break
                                                @case('liberation')
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                        <i class="bi bi-unlock me-1"></i>Libération annulation
                                                    </span>
                                                    @break
                                                @case('transfert_credit')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-arrow-down-left me-1"></i>Gain formation
                                                    </span>
                                                    @break
                                                @case('transfert_debit')
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                        <i class="bi bi-arrow-up-right me-1"></i>Règlement formation
                                                    </span>
                                                    @break
                                                @case('litige_transfert')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-hammer me-1"></i>Litige : Transfert
                                                    </span>
                                                    @break
                                                @case('litige_remboursement')
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Litige : Restitution
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="badge bg-light text-dark">{{ $tx->type }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($tx->reservation && $tx->reservation->slot && $tx->reservation->slot->offer)
                                                <span class="fw-semibold text-dark">{{ $tx->reservation->slot->offer->titre }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">
                                            {{ $tx->description }}
                                        </td>
                                        <td class="text-end pe-3 fw-bold">
                                            @if($tx->montant > 0)
                                                <span class="text-success">+{{ $tx->montant }} h</span>
                                            @elseif($tx->montant < 0)
                                                <span class="text-danger">{{ $tx->montant }} h</span>
                                            @else
                                                <span class="text-muted">0 h</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block text-secondary mb-1"></i>
                                            Aucun mouvement enregistré pour le moment.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($transactions->hasPages())
                    <div class="card-footer bg-white border-0 py-3">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
