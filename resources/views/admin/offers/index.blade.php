@extends('layouts.app')

@section('title', 'Modération des Offres')

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
                    <h3 class="fw-bold text-dark mb-1">Modération des Offres de Compétences</h3>
                    <p class="text-muted small mb-0">Masquez les offres inappropriées du catalogue public (RG17).</p>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card card-custom mb-4 p-3 border-0 shadow-sm">
                <form action="{{ route('admin.offers.index') }}" method="GET" class="row g-2">
                    <div class="col-md-7">
                        <input type="text" name="q" class="form-control" placeholder="Rechercher par titre ou compétence..." value="{{ request('q') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="masked" class="form-select">
                            <option value="">Tous les statuts de modération</option>
                            <option value="1" {{ request('masked') == '1' ? 'selected' : '' }}>Masquées uniquement</option>
                            <option value="0" {{ request('masked') == '0' ? 'selected' : '' }}>Visibles uniquement</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Filtrer</button>
                    </div>
                </form>
            </div>

            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>Offres enregistrées ({{ $offers->total() }})</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Titre de l'offre</th>
                                    <th>Auteur</th>
                                    <th>Catégorie</th>
                                    <th>Durée (RG02)</th>
                                    <th>Statut publication</th>
                                    <th>Modération (RG17)</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($offers as $off)
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('offers.show', $off) }}" class="fw-bold text-dark text-decoration-none d-block">
                                                {{ $off->titre }}
                                            </a>
                                            <span class="small text-muted">{{ $off->competence }} &bull; {{ ucfirst($off->mode) }}</span>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold">{{ $off->user->nom_complet }}</span>
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ $off->user->email }}</div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border">{{ $off->category->nom }}</span></td>
                                        <td><strong>{{ $off->duree }} heure(s)</strong></td>
                                        <td>
                                            @switch($off->status)
                                                @case('publiee')
                                                    <span class="badge bg-success-subtle text-success">Publiée</span>
                                                    @break
                                                @case('brouillon')
                                                    <span class="badge bg-secondary-subtle text-secondary">Brouillon</span>
                                                    @break
                                                @case('archivee')
                                                    <span class="badge bg-danger-subtle text-danger">Archivée</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($off->is_masked)
                                                <span class="badge bg-danger">Masquée (RG17)</span>
                                            @else
                                                <span class="badge bg-success">Visible</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <form action="{{ route('admin.offers.toggleMask', $off) }}" method="POST" class="d-inline" onsubmit="return confirm('Modifier la visibilité de cette offre ? (RG17)');">
                                                @csrf
                                                @if($off->is_masked)
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Démasquer">
                                                        <i class="bi bi-eye me-1"></i>Démasquer
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Masquer l'offre">
                                                        <i class="bi bi-eye-slash me-1"></i>Masquer (RG17)
                                                    </button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted small">Aucune offre trouvée.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($offers->hasPages())
                    <div class="card-footer bg-white border-0 py-3">
                        {{ $offers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
