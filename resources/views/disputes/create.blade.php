@extends('layouts.app')

@section('title', 'Signaler un problème sur une séance')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card card-custom border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-danger-subtle text-danger rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-shield-exclamation fs-2"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Signaler un désaccord ou une absence</h4>
                        <p class="text-muted small">
                            En cas d'absence d'un participant ou de non-conformité de la séance, ouvrez un litige. Les crédits resteront bloqués jusqu'à la décision de l'administrateur (RG12, RG13).
                        </p>
                    </div>

                    <div class="card bg-light border-0 p-3 mb-4">
                        <div class="fw-bold text-dark">{{ $reservation->slot->offer->titre }}</div>
                        <div class="small text-muted">
                            Séance du {{ $reservation->slot->date_debut->format('d/m/Y H:i') }} &bull; Durée : {{ $reservation->slot->offer->duree }}h
                        </div>
                    </div>

                    <form action="{{ route('disputes.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">

                        <div class="mb-3">
                            <label for="motif" class="form-label small fw-semibold">Motif du signalement</label>
                            <select name="motif" id="motif" class="form-select @error('motif') is-invalid @enderror" required>
                                <option value="">Sélectionnez un motif</option>
                                <option value="Absence du formateur">Absence du formateur</option>
                                <option value="Absence du bénéficiaire">Absence du bénéficiaire</option>
                                <option value="Séance écourtée ou non réalisée">Séance écourtée ou non réalisée</option>
                                <option value="Problème technique / Lien inaccessible">Problème technique / Lien inaccessible</option>
                                <option value="Autre désaccord majeur">Autre désaccord majeur</option>
                            </select>
                            @error('motif') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label small fw-semibold">Explications détaillées pour l'administrateur</label>
                            <textarea name="description" id="description" rows="5" class="form-control @error('description') is-invalid @enderror" placeholder="Expliquez ce qui s'est produit pour permettre à l'administrateur de trancher équitablement..." required>{{ old('description') }}</textarea>
                            <div class="form-text small">Ces éléments seront consultables par les deux parties et par l'administrateur (RG19).</div>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('planning') }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-danger px-4 fw-bold">
                                <i class="bi bi-shield-lock-fill me-1"></i> Ouvrir le litige (Conserver crédits bloqués)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
