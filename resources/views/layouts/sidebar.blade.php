<div class="sidebar p-3">
    <div class="d-flex align-items-center gap-3 pb-3 mb-3 border-bottom">
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-weight: 700; font-size: 1.2rem;">
            {{ strtoupper(substr(Auth::user()->prenom, 0, 1)) }}
        </div>
        <div>
            <div class="fw-bold text-truncate" style="max-width: 150px;">{{ Auth::user()->nom_complet }}</div>
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle">
                {{ Auth::user()->role === 'admin' ? 'Administrateur' : 'Étudiant' }}
            </div>
        </div>
    </div>

    <!-- Navigation Étudiant -->
    <div class="text-uppercase text-muted fw-bold small mb-2 px-2" style="font-size: 0.75rem;">Espace Étudiant</div>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('offers.my') }}" class="nav-link {{ request()->routeIs('offers.my*') ? 'active' : '' }}">
                <i class="bi bi-journal-bookmark"></i> Mes Offres & Créneaux
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('reservations.index') }}" class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}">
                <i class="bi bi-inbox"></i> Demandes & Réservations
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('planning') }}" class="nav-link {{ request()->routeIs('planning') ? 'active' : '' }}">
                <i class="bi bi-calendar-week"></i> Mon Planning
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('wallet.index') }}" class="nav-link {{ request()->routeIs('wallet.*') ? 'active' : '' }}">
                <i class="bi bi-wallet2"></i> Portefeuille & Crédits
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('profile.show') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                <i class="bi bi-person-gear"></i> Mon Profil & Compétences
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('reviews.index') }}" class="nav-link {{ request()->routeIs('reviews.*') ? 'active' : '' }}">
                <i class="bi bi-star"></i> Mes Évaluations
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('disputes.my') }}" class="nav-link {{ request()->routeIs('disputes.my*') ? 'active' : '' }}">
                <i class="bi bi-shield-exclamation"></i> Mes Litiges
            </a>
        </li>
    </ul>

    <!-- Navigation Administrateur (si role=admin) -->
    @if(Auth::user()->isAdmin())
        <hr class="my-3">
        <div class="text-uppercase text-danger fw-bold small mb-2 px-2" style="font-size: 0.75rem;">Administration</div>
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}" class="nav-link text-danger {{ request()->routeIs('admin.dashboard') ? 'active bg-danger text-white' : '' }}">
                    <i class="bi bi-graph-up"></i> Statistiques & KPI
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.users.index') }}" class="nav-link text-danger {{ request()->routeIs('admin.users.*') ? 'active bg-danger text-white' : '' }}">
                    <i class="bi bi-people"></i> Gestion des Comptes
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.categories.index') }}" class="nav-link text-danger {{ request()->routeIs('admin.categories.*') ? 'active bg-danger text-white' : '' }}">
                    <i class="bi bi-tags"></i> Gestion Catégories
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.offers.index') }}" class="nav-link text-danger {{ request()->routeIs('admin.offers.*') ? 'active bg-danger text-white' : '' }}">
                    <i class="bi bi-eye-slash"></i> Modération des Offres
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.disputes.index') }}" class="nav-link text-danger {{ request()->routeIs('admin.disputes.*') ? 'active bg-danger text-white' : '' }}">
                    <i class="bi bi-hammer"></i> Arbitrage des Litiges
                </a>
            </li>
        </ul>
    @endif
</div>
