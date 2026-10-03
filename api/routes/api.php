<?php

declare(strict_types=1);

use App\Domain\Account\Enums\TokenAbility;
use App\Http\Controllers\Auth\AgentTokenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/*
 * Routes de l'API SamaCloud (préfixe automatique : /api).
 * Elles sont ajoutées module par module : authentification, projets, déploiements…
 */

// --- Authentification : routes publiques, avec une limite d'essais renforcée ---
Route::middleware('throttle:auth')->group(function () {
    Route::post('inscription', RegisterController::class)->name('auth.register');
    Route::post('connexion', LoginController::class)->name('auth.login');
});

// --- Routes qui exigent un jeton valide et un compte non suspendu ---
Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('moi', MeController::class)->name('auth.me');

    // Réservé à la session du navigateur : un jeton d'agent IA n'a pas la capacité « jetons:gerer ».
    Route::middleware('abilities:'.TokenAbility::ManageTokens->value)->group(function () {
        Route::post('deconnexion', LogoutController::class)->name('auth.logout');

        Route::get('jetons-ia', [AgentTokenController::class, 'index'])->name('agent-tokens.index');
        Route::post('jetons-ia', [AgentTokenController::class, 'store'])->name('agent-tokens.store');
        Route::delete('jetons-ia/{id}', [AgentTokenController::class, 'destroy'])
            ->whereNumber('id')
            ->name('agent-tokens.destroy');
    });
});
