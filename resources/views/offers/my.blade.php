@extends('layouts.app')

@section('title', 'Mes Offres de Compétences')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 col-xl-2 d-none d-lg-block">
            @include('layouts.sidebar')
        </div>

        <!-- Contenu principal -->
        <div class="col-lg-9 col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold text-dark mb-1">Mes Offres de Compétences & Créneaux</h3>
                    <p class="text-muted small mb-0">Gérez les séances que vous proposez aux autres étudiants du campus.</p>
                </div>
                <a href="{{ route('offers.create') }}" class="btn btn-brand px-3 shadow-sm">
                    <i class="bi bi-plus-circle me-1"></i> Créer une nouvelle offre
                </a>
            </div>

            <div class="row g-4">
                @forelse($offers as $offer)
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header card-header-custom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <h5 class="fw-bold mb-0 text-dark">{{ $offer->titre }}</h5>
                                    @switch($offer->status)
                                        @case('publiee')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Publiée</span>
                                            @break
                                        @case('brouillon')
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Brouillon</span>
                                            @break
                                        @case('archivee')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Archivée (RG17)</span>
                                            @break
                                    @endswitch
                                    @if($offer->is_masked)
                                        <span class="badge bg-danger">Masquée par modération (RG17)</span>
                                    @endif
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="{{ route('offers.show', $offer) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Aperçu
                                    </a>
                                    <a href="{{ route('offers.edit', $offer) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil me-1"></i>Modifier
                                    </a>
                                    @if($offer->status === 'brouillon' || $offer->status === 'archivee')
                                        <form action="{{ route('offers.publish', $offer) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-upload me-1"></i>Publier
                                            </button>
                                        </form>
                                    @elseif($offer->status === 'publiee')
                                        <form action="{{ route('offers.archive', $offer) }}" method="POST" class="d-inline" onsubmit="return confirm('Archiver cette offre annulera toutes les demandes en attente mais préservera les séances déjà acceptées (RG17). Confirmer ?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-archive me-1"></i>Archiver (RG17)
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="card-body p-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Catégorie :</span>
                                        <strong class="text-dark">{{ $offer->category->nom }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Compétence :</span>
                                        <strong class="text-dark">{{ $offer->competence }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Durée / Coût :</span>
                                        <strong class="text-dark">{{ $offer->duree }}h ({{ $offer->duree }} crédit)</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Mode :</span>
                                        <strong class="text-dark">{{ ucfirst($offer->mode) }}</strong>
                                    </div>
                                </div>

                                <p class="text-muted small mb-4">{{ $offer->description }}</p>

                                <!-- Section Créneaux associés -->
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold mb-0 text-dark">
                                            <i class="bi bi-clock-history me-1 text-primary"></i> Créneaux de cette offre ({{ $offer->slots->count() }})
                                        </h6>
                                        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addSlotForm{{ $offer->id }}">
                                            <i class="bi bi-plus-lg me-1"></i>Ajouter un créneau
                                        </button>
                                    </div>

                                    <!-- Formulaire accordéon d'ajout de créneau -->
                                    <div class="collapse mb-3" id="addSlotForm{{ $offer->id }}">
                                        <div class="card card-body border-0 shadow-sm">
                                            <form action="{{ route('slots.store', $offer) }}" method="POST">
                                                @csrf
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-5">
                                                        <label class="form-label small fw-semibold">Début de la séance</label>
                                                        <input type="datetime-local" name="date_debut" class="form-control form-control-sm" required min="{{ now()->addMinutes(30)->format('Y-m-d\TH:i') }}">
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label class="form-label small fw-semibold">Lieu ou Lien visio privé (RG19)</label>
                                                        <input type="text" name="lieu_ou_lien" class="form-control form-control-sm" placeholder="Ex: Salle B12 ou lien Google Meet" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="submit" class="btn btn-brand btn-sm w-100 fw-bold">Valider</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Liste des créneaux existants -->
                                    <div class="table-responsive bg-white rounded border">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light small text-uppercase">
                                                <tr>
                                                    <th class="ps-3">Date & Heure</th>
                                                    <th>Statut</th>
                                                    <th>Lieu ou Lien (privé RG19)</th>
                                                    <th>Demandes reçues</th>
                                                    <th class="text-end pe-3">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($offer->slots->sortBy('date_debut') as $slot)
                                                    <tr>
                                                        <td class="ps-3 text-nowrap">
                                                            <strong>{{ $slot->date_debut->format('d/m/Y H:i') }}</strong> &rarr; {{ $slot->date_fin->format('H:i') }}
                                                        </td>
                                                        <td>
                                                            @if($slot->status === 'disponible')
                                                                <span class="badge bg-success-subtle text-success">Disponible</span>
                                                            @elseif($slot->status === 'reserve')
                                                                <span class="badge bg-primary-subtle text-primary">Séance Réservée</span>
                                                            @else
                                                                <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($slot->status) }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="small text-truncate" style="max-width: 220px;" title="{{ $slot->lieu_ou_lien }}">
                                                            <i class="bi bi-geo-alt me-1 text-danger"></i>{{ $slot->lieu_ou_lien }}
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border">{{ $slot->reservations->count() }} demande(s)</span>
                                                        </td>
                                                        <td class="text-end pe-3">
                                                            @if($slot->status === 'disponible')
                                                                <form action="{{ route('slots.destroy', $slot) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce créneau ?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Supprimer ce créneau">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="text-muted small">Verrouillé</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center py-3 text-muted small">
                                                            Aucun créneau programmé pour cette offre. Cliquez sur "Ajouter un créneau" pour ouvrir les réservations !
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
                @empty
                    <div class="col-12 text-center py-5">
                        <div class="bg-light p-5 rounded-4 border">
                            <i class="bi bi-journal-plus fs-1 text-secondary d-block mb-2"></i>
                            <h5 class="fw-bold text-dark">Vous n'avez pas encore créé d'offre</h5>
                            <p class="text-muted small">Partagez votre maîtrise en programmation, langues ou méthodologie pour accumuler des crédits !</p>
                            <a href="{{ route('offers.create') }}" class="btn btn-brand btn-sm mt-2">Créer ma première offre</a>
                        </div>
                    </div>
                @endforelse
            </div>

            @if($offers->hasPages())
                <div class="mt-4 d-flex justify-content-center">
                    {{ $offers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
