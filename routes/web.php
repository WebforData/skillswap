<?php

use Illuminate\Http\Request;
// Contrôleurs Admin
use Illuminate\Support\Facades\Route;

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
Route::get('/', fn () => view('app'))->name('home');
Route::get('/connexion', fn () => view('app'))->name('login');
Route::get('/inscription', fn () => view('app'))->name('register');
Route::get('/catalogue', fn () => view('app'))->name('catalogue');
Route::get('/app/{any?}', fn () => view('app'))->where('any', '.*')->name('spa');
Route::get('/admin/{any?}', fn () => view('app'))->where('any', '.*')->name('admin.spa');

Route::fallback(function (Request $request) {
    if ($request->is('api/*', 'mcp', 'mcp/*', '.well-known/*')) {
        return response()->json(['message' => 'Ressource non trouvée.'], 404);
    }

    return view('app');
});
