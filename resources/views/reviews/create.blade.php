@extends('layouts.app')

@section('title', 'Évaluer la séance')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card card-custom shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-warning-subtle text-warning rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-star-fill fs-2"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Évaluation de la séance</h4>
                        <p class="text-muted small">
                            Partagez votre retour d'expérience sur la séance réalisée avec <strong>{{ $evaluatedUser->nom_complet }}</strong> (RG15).
                        </p>
                    </div>

                    <div class="card bg-light border-0 p-3 mb-4 text-center">
                        <div class="fw-bold text-dark">{{ $reservation->slot->offer->titre }}</div>
                        <div class="small text-muted">
                            Séance du {{ $reservation->slot->date_debut->format('d/m/Y') }} &bull; Durée : {{ $reservation->slot->offer->duree }}h
                        </div>
                    </div>

                    <form action="{{ route('reviews.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">

                        <!-- Sélection de la note (1 à 5 étoiles) -->
                        <div class="mb-4 text-center">
                            <label class="form-label fw-bold d-block mb-2">Votre note (de 1 à 5 étoiles)</label>
                            <div class="d-flex justify-content-center gap-2 fs-2 text-warning" id="starContainer">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star star-btn" data-value="{{ $i }}" style="cursor: pointer;"></i>
                                @endfor
                            </div>
                            <input type="hidden" name="note" id="noteInput" value="{{ old('note', 5) }}">
                            <div class="form-text small mt-1" id="starDescription">5/5 - Excellente séance</div>
                            @error('note') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <!-- Commentaire -->
                        <div class="mb-4">
                            <label for="commentaire" class="form-label small fw-semibold">Votre commentaire (optionnel)</label>
                            <textarea name="commentaire" id="commentaire" rows="4" class="form-control" placeholder="Qu'avez-vous particulièrement apprécié ? Comment s'est passée la pédagogie ou l'écoute ?">{{ old('commentaire') }}</textarea>
                            <div class="form-text small">Visible sur le profil public de l'étudiant.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('reservations.index') }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-brand px-4 fw-bold">
                                <i class="bi bi-send-check me-1"></i> Publier mon évaluation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const stars = document.querySelectorAll('.star-btn');
        const input = document.getElementById('noteInput');
        const desc = document.getElementById('starDescription');

        const descriptions = {
            1: "1/5 - Très insatisfaisant",
            2: "2/5 - Décevant",
            3: "3/5 - Moyen / Conforme",
            4: "4/5 - Très bien",
            5: "5/5 - Excellente séance"
        };

        function updateStars(val) {
            input.value = val;
            stars.forEach(s => {
                const sVal = parseInt(s.dataset.value);
                if (sVal <= val) {
                    s.classList.remove('bi-star');
                    s.classList.add('bi-star-fill');
                } else {
                    s.classList.remove('bi-star-fill');
                    s.classList.add('bi-star');
                }
            });
            desc.textContent = descriptions[val];
        }

        stars.forEach(s => {
            s.addEventListener('click', function () {
                updateStars(parseInt(this.dataset.value));
            });
        });

        // Init avec valeur existante
        updateStars(parseInt(input.value) || 5);
    });
</script>
@endpush
@endsection
