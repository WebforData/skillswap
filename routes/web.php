<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\NotificationController;

// Contrôleurs Admin
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OfferModerationController as AdminOfferModerationController;
use App\Http\Controllers\Admin\DisputeResolutionController as AdminDisputeResolutionController;

/*
|--------------------------------------------------------------------------
| Application SPA React 19 (SkillSwap Campus)
|--------------------------------------------------------------------------
|
| Point d'ancrage pour le montage de l'application React + TypeScript.
| Toutes les URLs web servent la vue hôte 'app.blade.php' pour le routage client.
| Les routes API '/api/*' sont strictement préservées et ne sont jamais capturées.
|
*/
Route::get('/', fn() => view('app'))->name('home');
Route::get('/connexion', fn() => view('app'))->name('login');
Route::get('/inscription', fn() => view('app'))->name('register');
Route::get('/catalogue', fn() => view('app'))->name('catalogue');
Route::get('/app/{any?}', fn() => view('app'))->where('any', '.*')->name('spa');
Route::get('/admin/{any?}', fn() => view('app'))->where('any', '.*')->name('admin.spa');

Route::fallback(function (\Illuminate\Http\Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Ressource non trouvée.'], 404);
    }
    return view('app');
});
