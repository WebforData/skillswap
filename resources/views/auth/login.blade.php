@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card card-custom border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-box-arrow-in-right fs-2"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Connexion</h3>
                        <p class="text-muted small">Accédez à votre espace SkillSwap Campus</p>
                    </div>

                    <form action="{{ route('login.post') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-semibold">Adresse électronique universitaire</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="etudiant@skillswap.edu" required autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-semibold">Mot de passe</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label small" for="remember">Se souvenir de moi</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2 fs-6 shadow-sm mb-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
                        </button>

                        <div class="text-center text-muted small">
                            Pas encore de compte ? <a href="{{ route('register') }}" class="text-primary fw-semibold text-decoration-none">Créer un compte</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
