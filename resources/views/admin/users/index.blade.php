@extends('layouts.app')

@section('title', 'Gestion des Étudiants')

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
                    <h3 class="fw-bold text-dark mb-1">Gestion des Comptes Étudiants</h3>
                    <p class="text-muted small mb-0">Supervisez les inscriptions, les soldes et activez/désactivez des comptes selon les règles (RG18).</p>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card card-custom mb-4 p-3 border-0 shadow-sm">
                <form action="{{ route('admin.users.index') }}" method="GET" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" name="q" class="form-control" placeholder="Rechercher par nom, email, filière..." value="{{ request('q') }}">
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="actif" {{ request('status') == 'actif' ? 'selected' : '' }}>Actifs uniquement</option>
                            <option value="desactive" {{ request('status') == 'desactive' ? 'selected' : '' }}>Désactivés uniquement</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Filtrer</button>
                    </div>
                </form>
            </div>

            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-people me-2 text-primary"></i>Liste des étudiants ({{ $users->total() }})</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Étudiant</th>
                                    <th>Filière & Niveau</th>
                                    <th>Soldes (Dispo / Bloqué)</th>
                                    <th>Offres créées</th>
                                    <th>Statut (RG18)</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $u)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark">{{ $u->nom_complet }}</div>
                                            <div class="small text-muted">{{ $u->email }}</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold">{{ $u->filiere ?? 'Non renseignée' }}</div>
                                            <div class="small text-muted">{{ $u->niveau_etudes ?? '' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success me-1">{{ $u->solde_disponible }}h dispo</span>
                                            @if($u->solde_bloque > 0)
                                                <span class="badge bg-warning-subtle text-warning">{{ $u->solde_bloque }}h bloqué</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $u->offers_count }} offre(s)</span>
                                        </td>
                                        <td>
                                            @if($u->is_active)
                                                <span class="badge bg-success">Actif</span>
                                            @else
                                                <span class="badge bg-danger">Désactivé (RG18)</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <form action="{{ route('admin.users.toggleStatus', $u) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirmer la modification du statut de ce compte ? (RG18)');">
                                                @csrf
                                                @if($u->is_active)
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Désactiver le compte">
                                                        <i class="bi bi-person-x me-1"></i>Désactiver
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Réactiver le compte">
                                                        <i class="bi bi-person-check me-1"></i>Réactiver
                                                    </button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted small">Aucun étudiant trouvé.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($users->hasPages())
                    <div class="card-footer bg-white border-0 py-3">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
