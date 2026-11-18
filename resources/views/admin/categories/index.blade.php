@extends('layouts.app')

@section('title', 'Gestion des Catégories')

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
                    <h3 class="fw-bold text-dark mb-1">Gestion des Catégories de Compétences</h3>
                    <p class="text-muted small mb-0">Organisez l'arborescence des domaines universitaires.</p>
                </div>
                <button type="button" class="btn btn-brand px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-circle me-1"></i> Ajouter une catégorie
                </button>
            </div>

            <div class="card card-custom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">Icône & Nom</th>
                                    <th>Slug</th>
                                    <th>Description</th>
                                    <th>Offres rattachées</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $cat)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-primary-subtle text-primary rounded p-2">
                                                    <i class="bi {{ $cat->icon ?? 'bi-tag' }} fs-5"></i>
                                                </div>
                                                <strong class="text-dark">{{ $cat->nom }}</strong>
                                            </div>
                                        </td>
                                        <td><code class="small">{{ $cat->slug }}</code></td>
                                        <td class="small text-muted text-truncate" style="max-width: 300px;">{{ $cat->description }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $cat->offers_count }} offre(s)</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">Aucune catégorie enregistrée.</td>
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

<!-- Modale Ajout Catégorie -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.categories.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Ajouter une nouvelle catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cat_nom" class="form-label small fw-semibold">Nom de la catégorie</label>
                        <input type="text" name="nom" id="cat_nom" class="form-control" placeholder="Ex: Réseaux & Télécoms" required>
                    </div>
                    <div class="mb-3">
                        <label for="cat_icon" class="form-label small fw-semibold">Icône Bootstrap Icons</label>
                        <input type="text" name="icon" id="cat_icon" class="form-control" placeholder="Ex: bi-hdd-network">
                        <div class="form-text small">Nom de classe Bootstrap Icons (ex: bi-code-slash, bi-translate).</div>
                    </div>
                    <div class="mb-3">
                        <label for="cat_desc" class="form-label small fw-semibold">Description</label>
                        <textarea name="description" id="cat_desc" rows="3" class="form-control" placeholder="Brève description du domaine..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-brand btn-sm fw-bold">Créer la catégorie</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
