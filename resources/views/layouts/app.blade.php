<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SkillSwap Campus') - Plateforme Universitaire d'Échange de Compétences</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #1a56db;
            --brand-navy: #0d2847;
            --brand-light: #f0f5ff;
            --brand-accent: #0284c7;
            --brand-bg: #f8fafc;
            --brand-card-border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--brand-bg);
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar-custom {
            background-color: var(--brand-navy);
            box-shadow: 0 2px 8px rgba(13, 40, 71, 0.12);
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #ffffff !important;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            color: #ffffff !important;
        }

        /* Badges de crédits */
        .badge-credit-disp {
            background-color: #10b981;
            color: #ffffff;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 9999px;
        }
        .badge-credit-bloq {
            background-color: #f59e0b;
            color: #ffffff;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 9999px;
        }

        /* Sidebar */
        .sidebar {
            background-color: #ffffff;
            border-right: 1px solid var(--brand-card-border);
            min-height: calc(100vh - 70px);
        }
        .sidebar .nav-link {
            color: #475569 !important;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sidebar .nav-link:hover {
            background-color: var(--brand-light);
            color: var(--brand-primary) !important;
        }
        .sidebar .nav-link.active {
            background-color: var(--brand-primary);
            color: #ffffff !important;
        }

        /* Cards & UI */
        .card-custom {
            border: 1px solid var(--brand-card-border);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            background: #ffffff;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .card-custom:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .card-header-custom {
            background: #ffffff;
            border-bottom: 1px solid var(--brand-card-border);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        .btn-brand {
            background-color: var(--brand-primary);
            color: #ffffff;
            font-weight: 600;
            border: none;
        }
        .btn-brand:hover {
            background-color: #1442a8;
            color: #ffffff;
        }

        /* Footer */
        footer {
            margin-top: auto;
            background-color: #ffffff;
            border-top: 1px solid var(--brand-card-border);
        }
    </style>
    @stack('styles')
</head>
<body>
    @include('layouts.navigation')

    <main class="flex-grow-1">
        @if(session('success') || session('error') || session('warning') || session('info') || $errors->any())
            <div class="container mt-3">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                        <div>{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <div>{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 me-2"></i>
                        <div>{{ session('warning') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('info'))
                    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                        <div>{{ session('info') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-x-circle-fill fs-5 me-2"></i>
                            <strong>Veuillez corriger les erreurs suivantes :</strong>
                        </div>
                        <ul class="mb-0 ps-4">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>
        @endif

        @yield('content')
    </main>

    @include('layouts.footer')

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>
