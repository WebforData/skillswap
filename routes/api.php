<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminOffreController;
use App\Http\Controllers\Api\Admin\AdminStatsController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreneauController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\LitigeController;
use App\Http\Controllers\Api\OffreController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SkillSwap Campus
|--------------------------------------------------------------------------
|
| Toutes les routes définies ici sont automatiquement préfixées par '/api'.
| L'authentification des requêtes sécurisées est assurée par Laravel Sanctum.
|
*/

// Routes publiques d'authentification
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
});

use App\Http\Controllers\Api\Admin\AdminReservationController;
use App\Http\Controllers\Api\StudentController;

Route::get('/categories', [StudentController::class, 'categories']);

// Routes publiques de consultation
Route::get('/offres', [OffreController::class, 'index'])->name('api.offres.index');
Route::get('/offres/{id}', [OffreController::class, 'show'])->whereNumber('id')->name('api.offres.show');
Route::get('/users/{id}/evaluations', [EvaluationController::class, 'publicReviews'])->whereNumber('id');
Route::get('/users/{id}/evaluations/stats', [EvaluationController::class, 'getStats'])->whereNumber('id')->name('api.users.evaluations.stats');

// Routes protégées par Sanctum (authentification obligatoire + vérification compte actif RG18)
Route::middleware(['auth:sanctum', 'auth.active'])->group(function () {
    Route::put('/auth/profile', [StudentController::class, 'updateProfile']);
    Route::get('/wallet/history', [StudentController::class, 'wallet']);
    Route::get('/suggestions', [StudentController::class, 'suggestions']);
    Route::get('/notifications', [StudentController::class, 'notifications']);
    Route::patch('/notifications/{id}/read', [StudentController::class, 'readNotification'])->whereNumber('id');

    // Authentification & profil
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
    });

    // Réservations & Cycle de vie des séances (RG02 à RG19)
    Route::prefix('reservations')->group(function () {
        Route::get('/', [ReservationController::class, 'index'])->name('api.reservations.index');
        Route::post('/', [ReservationController::class, 'store'])->name('api.reservations.store');
        Route::get('/{id}', [ReservationController::class, 'show'])->name('api.reservations.show');
        Route::post('/{id}/accepter', [ReservationController::class, 'accepter'])->name('api.reservations.accepter');
        Route::post('/{id}/refuser', [ReservationController::class, 'refuser'])->name('api.reservations.refuser');
        Route::post('/{id}/retirer', [ReservationController::class, 'retirer'])->name('api.reservations.retirer');
        Route::post('/{id}/annuler', [ReservationController::class, 'annuler'])->name('api.reservations.annuler');
        Route::post('/{id}/confirmer', [ReservationController::class, 'confirmer'])->name('api.reservations.confirmer');
        Route::post('/{id}/litige', [ReservationController::class, 'ouvrirLitige'])->name('api.reservations.litige');
    });

    Route::get('/mes-litiges', [LitigeController::class, 'mine']);
    Route::post('/litiges/{id}/statements', [LitigeController::class, 'statement'])->whereNumber('id');

    // Arbitrage administratif des litiges (RG13, RG14)
    Route::prefix('litiges')->group(function () {
        Route::get('/', [LitigeController::class, 'index'])->name('api.litiges.index');
        Route::get('/{id}', [LitigeController::class, 'show'])->whereNumber('id')->name('api.litiges.show');
        Route::post('/{id}/arbitrer', [LitigeController::class, 'arbitrer'])->name('api.litiges.arbitrer');
    });

    // Évaluations bilatérales post-séance (RG15)
    Route::prefix('evaluations')->group(function () {
        Route::post('/', [EvaluationController::class, 'store'])->name('api.evaluations.store');
        Route::get('/recues', [EvaluationController::class, 'indexRecues'])->name('api.evaluations.recues');
        Route::get('/donnees', [EvaluationController::class, 'indexDonnees'])->name('api.evaluations.donnees');
    });

    // Offres d'échange de compétences (RG04, RG17)
    Route::prefix('offres')->group(function () {
        Route::get('/mes-offres', [OffreController::class, 'mesOffres'])->name('api.offres.mes-offres');
        Route::post('/', [OffreController::class, 'store'])->name('api.offres.store');
        Route::put('/{id}', [OffreController::class, 'update'])->name('api.offres.update');
        Route::post('/{id}/publier', [OffreController::class, 'publier'])->name('api.offres.publier');
        Route::post('/{id}/brouillon', [OffreController::class, 'brouillon'])->name('api.offres.brouillon');
        Route::post('/{id}/archiver', [OffreController::class, 'archiver'])->name('api.offres.archiver');
        Route::post('/{id}/creneaux', [CreneauController::class, 'store'])->whereNumber('id')->name('api.offres.creneaux.store');
    });

    // Gestion des créneaux (RG02, RG07, RG08, RG17)
    Route::prefix('creneaux')->group(function () {
        Route::put('/{id}', [CreneauController::class, 'update'])->whereNumber('id')->name('api.creneaux.update');
        Route::delete('/{id}', [CreneauController::class, 'destroy'])->whereNumber('id')->name('api.creneaux.destroy');
    });

    // Planning consolidé de l'étudiant (RG08, RG19)
    Route::get('/planning', [PlanningController::class, 'index'])->name('api.planning.index');

    // =========================================================================
    // BACK-OFFICE ADMINISTRATEUR (RG18, RG17, Modération, Statistiques)
    // =========================================================================
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/reservations', [AdminReservationController::class, 'index']);
        Route::post('/reservations/{id}/settle', [AdminReservationController::class, 'settle'])->whereNumber('id');
        // Utilisateurs (RG18)
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('api.admin.users.index');
            Route::get('/{id}', [AdminUserController::class, 'show'])->whereNumber('id')->name('api.admin.users.show');
            Route::patch('/{id}/desactiver', [AdminUserController::class, 'desactiver'])->whereNumber('id')->name('api.admin.users.desactiver');
            Route::patch('/{id}/activer', [AdminUserController::class, 'activer'])->whereNumber('id')->name('api.admin.users.activer');
        });

        // Catégories
        Route::prefix('categories')->group(function () {
            Route::get('/', [AdminCategoryController::class, 'index'])->name('api.admin.categories.index');
            Route::post('/', [AdminCategoryController::class, 'store'])->name('api.admin.categories.store');
            Route::put('/{id}', [AdminCategoryController::class, 'update'])->whereNumber('id')->name('api.admin.categories.update');
            Route::delete('/{id}', [AdminCategoryController::class, 'destroy'])->whereNumber('id')->name('api.admin.categories.destroy');
        });

        // Offres (Modération & Archivage RG17)
        Route::prefix('offres')->group(function () {
            Route::get('/', [AdminOffreController::class, 'index'])->name('api.admin.offres.index');
            Route::get('/{id}', [AdminOffreController::class, 'show'])->whereNumber('id')->name('api.admin.offres.show');
            Route::patch('/{id}/archiver', [AdminOffreController::class, 'archiver'])->whereNumber('id')->name('api.admin.offres.archiver');
        });

        // Statistiques & Indicateurs
        Route::get('/stats', [AdminStatsController::class, 'index'])->name('api.admin.stats.index');
    });
});
