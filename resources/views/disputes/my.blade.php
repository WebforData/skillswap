@extends('layouts.app')

@section('title', 'Mes Litiges')

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
                    <h3 class="fw-bold text-dark mb-1">Mes Litiges & Signalements</h3>
                    <p class="text-muted small mb-0">Suivi des séances contestées faisant l'objet d'un examen par l'administration (RG13, RG19).</p>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-shield-exclamation text-danger me-2"></i>Dossiers de litiges ({{ $disputes->total() }})</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Séance contestée</th>
                                    <th>Déclaré par</th>
                                    <th>Motif</th>
                                    <th>Statut / Résolution (RG13)</th>
                                    <th>Décision de l'administrateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($disputes as $disp)
                                    <tr>
                                        <td class="ps-3">
                                            <strong class="text-dark d-block">{{ $disp->reservation->slot->offer->titre }}</strong>
                                            <span class="small text-muted">{{ $disp->reservation->slot->date_debut->format('d/m/Y H:i') }}</span>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold">{{ $disp->openedBy->nom_complet }}</span>
                                            <div class="text-muted" style="font-size: 0.75rem;">Le {{ $disp->created_at->format('d/m/Y') }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $disp->motif }}</span>
                                            <div class="small text-muted mt-1 text-truncate" style="max-width: 250px;">{{ $disp->description }}</div>
                                        </td>
                                        <td>
                                            @switch($disp->resolution)
                                                @case('en_cours')
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                        <i class="bi bi-hourglass-split me-1"></i>En cours d'examen
                                                    </span>
                                                    @break
                                                @case('transfert_formateur')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-check-circle me-1"></i>Tranché : Crédits versés au formateur (RG14)
                                                    </span>
                                                    @break
                                                @case('restitution_beneficiaire')
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Tranché : Remboursé au bénéficiaire (RG14)
                                                    </span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td class="small">
                                            @if($disp->decision_admin)
                                                <div class="p-2 rounded bg-light border">
                                                    <strong>Motivation :</strong> {{ $disp->decision_admin }}
                                                    <div class="text-muted" style="font-size: 0.7rem;">Décision du {{ $disp->resolved_at?->format('d/m/Y H:i') }} (Définitif - RG14)</div>
                                                </div>
                                            @else
                                                <span class="text-muted italic">En attente de délibération</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            <i class="bi bi-shield-check fs-2 text-success d-block mb-1"></i>
                                            Aucun litige enregistré. Tous vos échanges se sont déroulés sereinement !
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
