@extends('layouts.app')

@section('title', 'Tableau de bord Administrateur')

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
                    <h3 class="fw-bold text-dark mb-1">
                        <i class="bi bi-shield-lock-fill text-danger me-2"></i>Tableau de bord d'Administration
                    </h3>
                    <p class="text-muted small mb-0">Supervision globale, indicateurs universitaires et régulation de la banque de temps.</p>
                </div>
            </div>

            <!-- 5 KPIs officiels du cahier des charges -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-4">
                    <div class="card card-custom p-3 border-start border-4 border-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Comptes Actifs</div>
                                <div class="fs-3 fw-bold text-primary">{{ $comptesActifs }}</div>
                                <span class="small text-muted">{{ $comptesDesactives }} compte(s) désactivé(s)</span>
                            </div>
                            <div class="bg-primary-subtle text-primary rounded-circle p-3">
                                <i class="bi bi-people-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-4">
                    <div class="card card-custom p-3 border-start border-4 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Offres Publiées</div>
                                <div class="fs-3 fw-bold text-info">{{ $offresPubliees }}</div>
                                <span class="small text-muted">{{ $offresMasquees }} offre(s) masquée(s)</span>
                            </div>
                            <div class="bg-info-subtle text-info rounded-circle p-3">
                                <i class="bi bi-grid-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-4">
                    <div class="card card-custom p-3 border-start border-4 border-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Séances Terminées</div>
                                <div class="fs-3 fw-bold text-success">{{ $seancesRealisees }}</div>
                                <span class="small text-muted">{{ $seancesAcceptees }} acceptée(s) à venir</span>
                            </div>
                            <div class="bg-success-subtle text-success rounded-circle p-3">
                                <i class="bi bi-check2-all fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-6">
                    <div class="card card-custom p-3 border-start border-4 border-warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Total Crédits Transférés</div>
                                <div class="fs-3 fw-bold text-warning">{{ $creditsTransferes }} heures</div>
                                <span class="small text-muted">Échanges réalisés sans flux financier</span>
                            </div>
                            <div class="bg-warning-subtle text-warning rounded-circle p-3">
                                <i class="bi bi-bank fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-6">
                    <div class="card card-custom p-3 border-start border-4 border-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Litiges Ouverts</div>
                                <div class="fs-3 fw-bold text-danger">{{ $litigesOuverts }}</div>
                                <a href="{{ route('admin.disputes.index') }}" class="small text-decoration-none text-danger fw-semibold">
                                    Arbitrer les dossiers en attente &rarr;
                                </a>
                            </div>
                            <div class="bg-danger-subtle text-danger rounded-circle p-3">
                                <i class="bi bi-shield-exclamation fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique Chart.js & Activité Récente -->
            <div class="row g-4 mb-4">
                <!-- Graphique Chart.js : Répartition des compétences -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom">
                            <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Répartition des offres par catégorie
                        </div>
                        <div class="card-body p-4 d-flex align-items-center justify-content-center">
                            <div style="width: 100%; max-height: 280px;">
                                <canvas id="categoriesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Litiges nécessitant attention immédiate -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-danger">
                                <i class="bi bi-exclamation-octagon-fill me-2"></i>Litiges récents à traiter
                            </span>
                            <a href="{{ route('admin.disputes.index') }}" class="small text-decoration-none">Voir tout</a>
                        </div>
                        <div class="card-body p-0">
                            @forelse($recentDisputes as $disp)
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $disp->reservation->slot->offer->titre }}</div>
                                        <div class="small text-muted">
                                            Motif : <span class="badge bg-light text-dark border">{{ $disp->motif }}</span>
                                            &bull; Déclaré par {{ $disp->openedBy->nom_complet }}
                                        </div>
                                    </div>
                                    <div>
                                        @if($disp->isResolved())
                                            <span class="badge bg-success-subtle text-success">Clôturé</span>
                                        @else
                                            <a href="{{ route('admin.disputes.index') }}" class="btn btn-sm btn-danger fw-bold">
                                                Arbitrer
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    Aucun litige ouvert. Tout est sous contrôle !
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('categoriesChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    data: {!! json_encode($chartData) !!},
                    backgroundColor: [
                        '#1a56db',
                        '#10b981',
                        '#f59e0b',
                        '#8b5cf6',
                        '#ec4899',
                        '#0284c7'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: { family: 'Plus Jakarta Sans', size: 12 }
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
@endsection
