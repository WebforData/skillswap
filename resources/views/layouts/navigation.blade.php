<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <i class="bi bi-mortarboard-fill fs-4 text-warning"></i>
            <span>SkillSwap <span class="badge bg-primary text-white fs-6">Campus</span></span>
        </a>

        <button class="navbar-toggler text-white border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list fs-3"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <!-- Liens généraux -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                        <i class="bi bi-house-door me-1"></i> Accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('offers.index') ? 'active' : '' }}" href="{{ route('offers.index') }}">
                        <i class="bi bi-grid me-1"></i> Catalogue des offres
                    </a>
                </li>
                @auth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i> Mon Espace
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('planning') ? 'active' : '' }}" href="{{ route('planning') }}">
                            <i class="bi bi-calendar-check me-1"></i> Mon Planning
                        </a>
                    </li>
                @endauth
            </ul>

            <!-- Droite : Profil & Soldes -->
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                @auth
                    <!-- Portefeuille rapide en temps réel (RG16) -->
                    <li class="nav-item d-flex align-items-center gap-1 me-2" title="Solde de votre banque de temps">
                        <a href="{{ route('wallet.index') }}" class="text-decoration-none d-flex align-items-center gap-1">
                            <span class="badge-credit-disp" title="Crédits disponibles pour réserver">
                                <i class="bi bi-clock-fill me-1"></i>{{ Auth::user()->solde_disponible }}h dispo
                            </span>
                            @if(Auth::user()->solde_bloque > 0)
                                <span class="badge-credit-bloq" title="Crédits bloqués en attente de réalisation">
                                    <i class="bi bi-lock-fill me-1"></i>{{ Auth::user()->solde_bloque }}h bloqué
                                </span>
                            @endif
                        </a>
                    </li>

                    <!-- Notifications internes -->
                    <li class="nav-item dropdown me-2">
                        @php
                            $unreadNotifs = Auth::user()->notifications()->where('is_read', false)->take(5)->get();
                            $unreadCount = Auth::user()->notifications()->where('is_read', false)->count();
                        @endphp
                        <a class="nav-link position-relative px-2" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            @if($unreadCount > 0)
                                <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger">
                                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                    <span class="visually-hidden">notifications non lues</span>
                                </span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px;" aria-labelledby="notifDropdown">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Notifications</span>
                                @if($unreadCount > 0)
                                    <form action="{{ route('notifications.markAllAsRead') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Tout marquer comme lu</button>
                                    </form>
                                @endif
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            @forelse($unreadNotifs as $notif)
                                <li>
                                    <a class="dropdown-item py-2 text-wrap" href="{{ $notif->link ?? '#' }}">
                                        <div class="fw-semibold small text-primary">{{ $notif->titre }}</div>
                                        <div class="small text-muted text-truncate">{{ $notif->message }}</div>
                                        <div class="text-end text-muted" style="font-size: 0.75rem;">{{ $notif->created_at->diffForHumans() }}</div>
                                    </a>
                                </li>
                            @empty
                                <li class="text-center py-3 text-muted small">Aucune nouvelle notification</li>
                            @endforelse
                        </ul>
                    </li>

                    <!-- Menu Profil / Déconnexion -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: 600;">
                                {{ strtoupper(substr(Auth::user()->prenom, 0, 1)) }}
                            </div>
                            <span class="d-none d-md-inline">{{ Auth::user()->prenom }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold">{{ Auth::user()->nom_complet }}</div>
                                <div class="small text-muted">{{ Auth::user()->email }}</div>
                                <div class="badge bg-light text-dark mt-1">{{ Auth::user()->filiere ?? 'Étudiant' }}</div>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('profile.show') }}">
                                    <i class="bi bi-person me-2"></i> Mon Profil & Compétences
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('wallet.index') }}">
                                    <i class="bi bi-wallet2 me-2"></i> Portefeuille & Transactions
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('reservations.index') }}">
                                    <i class="bi bi-bookmark-check me-2"></i> Mes Réservations
                                </a>
                            </li>
                            @if(Auth::user()->isAdmin())
                                <li><hr class="dropdown-divider"></li>
                                <li class="dropdown-header text-uppercase fw-bold text-danger small">Administration</li>
                                <li>
                                    <a class="dropdown-item py-2 text-danger fw-semibold" href="{{ route('admin.dashboard') }}">
                                        <i class="bi bi-shield-lock-fill me-2"></i> Panneau d'Administration
                                    </a>
                                </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i> Se déconnecter
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Connexion
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-warning text-dark fw-bold btn-sm px-3" href="{{ route('register') }}">
                            <i class="bi bi-person-plus me-1"></i> Inscription (+2 crédits)
                        </a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
