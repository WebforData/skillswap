@extends('layouts.app')

@section('title', 'Inscription Étudiant')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card card-custom border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-person-plus-fill fs-2"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Inscription à SkillSwap</h3>
                        <p class="text-muted small">Créez votre compte et recevez automatiquement <strong>2 crédits gratuits</strong> pour vos premières séances ! (RG01)</p>
                    </div>

                    <form action="{{ route('register.post') }}" method="POST">
                        @csrf

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="prenom" class="form-label small fw-semibold">Prénom</label>
                                <input type="text" name="prenom" id="prenom" class="form-control @error('prenom') is-invalid @enderror" value="{{ old('prenom') }}" placeholder="Ex: Sara" required>
                                @error('prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="nom" class="form-label small fw-semibold">Nom</label>
                                <input type="text" name="nom" id="nom" class="form-control @error('nom') is-invalid @enderror" value="{{ old('nom') }}" placeholder="Ex: Alami" required>
                                @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-semibold">Adresse électronique universitaire</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="etudiant@skillswap.edu" required>
                            <div class="form-text small">Une seule inscription par adresse électronique (RG01).</div>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <label for="filiere" class="form-label small fw-semibold">Filière / Département</label>
                                <input type="text" name="filiere" id="filiere" class="form-control @error('filiere') is-invalid @enderror" value="{{ old('filiere') }}" placeholder="Ex: DUT Génie Informatique" required>
                                @error('filiere') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label for="niveau_etudes" class="form-label small fw-semibold">Niveau</label>
                                <select name="niveau_etudes" id="niveau_etudes" class="form-select @error('niveau_etudes') is-invalid @enderror" required>
                                    <option value="1ère année" {{ old('niveau_etudes') == '1ère année' ? 'selected' : '' }}>1ère année</option>
                                    <option value="2ème année" {{ old('niveau_etudes', '2ème année') == '2ème année' ? 'selected' : '' }}>2ème année (S3)</option>
                                    <option value="Licence / LP" {{ old('niveau_etudes') == 'Licence / LP' ? 'selected' : '' }}>Licence / LP</option>
                                    <option value="Master" {{ old('niveau_etudes') == 'Master' ? 'selected' : '' }}>Master</option>
                                </select>
                                @error('niveau_etudes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-semibold">Mot de passe</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label small fw-semibold">Confirmer le mot de passe</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2 fs-6 shadow-sm mb-3">
                            <i class="bi bi-gift-fill me-1"></i> Créer mon compte (+2 crédits gratuits)
                        </button>

                        <div class="text-center text-muted small">
                            Vous possédez déjà un compte ? <a href="{{ route('login') }}" class="text-primary fw-semibold text-decoration-none">Se connecter</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
