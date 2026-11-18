@extends('layouts.app')

@section('title', 'Mes Évaluations')

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
                    <h3 class="fw-bold text-dark mb-1">Mes Évaluations</h3>
                    <p class="text-muted small mb-0">Consultez les retours d'expérience reçus et donnés suite à vos séances réalisées (RG15).</p>
                </div>
                <div class="bg-white border rounded-pill px-3 py-2 shadow-sm d-flex align-items-center gap-2">
                    <span class="small text-muted">Votre moyenne générale :</span>
                    <i class="bi bi-star-fill text-warning"></i>
                    <strong class="fs-6 text-dark">{{ $user->note_moyenne }} / 5</strong>
                    <span class="text-muted small">({{ $user->nombre_avis }} avis)</span>
                </div>
            </div>

            <div class="row g-4">
                <!-- 1. Évaluations Reçues -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><i class="bi bi-chat-heart text-danger me-2"></i>Avis reçus ({{ $received->total() }})</span>
                        </div>
                        <div class="card-body p-3">
                            @forelse($received as $rev)
                                <div class="border rounded-3 p-3 mb-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong class="text-dark">{{ $rev->evaluator->nom_complet }}</strong>
                                            <div class="text-muted small">Séance : {{ $rev->reservation->slot->offer->titre }}</div>
                                        </div>
                                        <div class="text-warning">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="bi bi-star{{ $i <= $rev->note ? '-fill' : '' }}"></i>
                                            @endfor
                                        </div>
                                    </div>
                                    <p class="small text-secondary mb-2">{{ $rev->commentaire ?? 'Aucun commentaire textuel.' }}</p>
                                    <div class="text-end text-muted" style="font-size: 0.75rem;">
                                        Publié le {{ $rev->created_at->format('d/m/Y') }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    Aucune évaluation reçue pour le moment.
                                </div>
                            @endforelse
                        </div>
                        @if($received->hasPages())
                            <div class="card-footer bg-white border-0 py-2">
                                {{ $received->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 2. Évaluations Données -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Avis donnés aux pairs ({{ $given->total() }})</span>
                        </div>
                        <div class="card-body p-3">
                            @forelse($given as $rev)
                                <div class="border rounded-3 p-3 mb-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong class="text-dark">À l'attention de : {{ $rev->evaluated->nom_complet }}</strong>
                                            <div class="text-muted small">Séance : {{ $rev->reservation->slot->offer->titre }}</div>
                                        </div>
                                        <div class="text-warning">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="bi bi-star{{ $i <= $rev->note ? '-fill' : '' }}"></i>
                                            @endfor
                                        </div>
                                    </div>
                                    <p class="small text-secondary mb-2">{{ $rev->commentaire ?? 'Aucun commentaire textuel.' }}</p>
                                    <div class="text-end text-muted" style="font-size: 0.75rem;">
                                        Donné le {{ $rev->created_at->format('d/m/Y') }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    Vous n'avez pas encore déposé d'évaluation.
                                </div>
                            @endforelse
                        </div>
                        @if($given->hasPages())
                            <div class="card-footer bg-white border-0 py-2">
                                {{ $given->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
