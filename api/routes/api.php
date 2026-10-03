<?php

declare(strict_types=1);

use App\Domain\Account\Enums\TokenAbility;
use App\Domain\Community\UsernameRules;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Auth\AgentTokenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Community\AcceptedAnswerController;
use App\Http\Controllers\Community\AnswerController;
use App\Http\Controllers\Community\AnswerVoteController;
use App\Http\Controllers\Community\MyProfileController;
use App\Http\Controllers\Community\NotificationController;
use App\Http\Controllers\Community\ProfileController;
use App\Http\Controllers\Community\QuestionController;
use App\Http\Controllers\Community\ReportController;
use App\Http\Controllers\Community\TechnologyController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
 * Routes de l'API SamaCloud (préfixe automatique : /api).
 * Elles sont ajoutées module par module : authentification, projets, déploiements…
 */

// Format d'un nom de projet, identique à datacloud.schema.json. Un nom mal formé donne 404.
$projectName = '[a-z][a-z0-9-]{1,28}[a-z0-9]';

// --- Authentification : routes publiques, avec une limite d'essais renforcée ---
Route::middleware('throttle:auth')->group(function () {
    Route::post('inscription', RegisterController::class)->name('auth.register');
    Route::post('connexion', LoginController::class)->name('auth.login');
});

// --- Communauté : lecture publique, sans jeton (la vitrine doit être visible de tous) ---
Route::get('technologies', [TechnologyController::class, 'index'])->name('technologies.index');
Route::get('profils', [ProfileController::class, 'index'])->name('profiles.index');
Route::get('profils/{pseudo}', [ProfileController::class, 'show'])
    ->where('pseudo', trim(UsernameRules::PATTERN, '/^$'))
    ->name('profiles.show');
Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
Route::get('questions/{id}', [QuestionController::class, 'show'])->whereUlid('id')->name('questions.show');
Route::get('questions/{id}/reponses', [AnswerController::class, 'index'])->whereUlid('id')->name('answers.index');

// --- Routes qui exigent un jeton valide et un compte non suspendu ---
Route::middleware(['auth:sanctum', 'account.active'])->group(function () use ($projectName) {
    Route::get('moi', MeController::class)->name('auth.me');

    // Profil public du compte connecté. La modification est réservée à la session du navigateur.
    Route::get('moi/profil', [MyProfileController::class, 'show'])->name('my-profile.show');
    Route::patch('moi/profil', [MyProfileController::class, 'update'])
        ->middleware('abilities:'.TokenAbility::ManageProfile->value)
        ->name('my-profile.update');

    // Réservé à la session du navigateur : un jeton d'agent IA n'a pas la capacité « jetons:gerer ».
    Route::middleware('abilities:'.TokenAbility::ManageTokens->value)->group(function () {
        Route::post('deconnexion', LogoutController::class)->name('auth.logout');

        Route::get('jetons-ia', [AgentTokenController::class, 'index'])->name('agent-tokens.index');
        Route::post('jetons-ia', [AgentTokenController::class, 'store'])->name('agent-tokens.store');
        Route::delete('jetons-ia/{id}', [AgentTokenController::class, 'destroy'])
            ->whereNumber('id')
            ->name('agent-tokens.destroy');
    });

    // Participation à la communauté : réservée à la session du navigateur (un humain),
    // avec une limite de publications par minute contre le spam.
    Route::middleware(['abilities:'.TokenAbility::CommunityParticipate->value, 'throttle:publication'])->group(function () {
        Route::post('questions', [QuestionController::class, 'store'])->name('questions.store');
        Route::patch('questions/{id}', [QuestionController::class, 'update'])->whereUlid('id')->name('questions.update');
        Route::delete('questions/{id}', [QuestionController::class, 'destroy'])->whereUlid('id')->name('questions.destroy');

        Route::post('questions/{id}/reponses', [AnswerController::class, 'store'])->whereUlid('id')->name('answers.store');
        Route::patch('reponses/{id}', [AnswerController::class, 'update'])->whereUlid('id')->name('answers.update');
        Route::delete('reponses/{id}', [AnswerController::class, 'destroy'])->whereUlid('id')->name('answers.destroy');

        Route::put('questions/{id}/reponse-acceptee', [AcceptedAnswerController::class, 'update'])->whereUlid('id')->name('accepted-answer.update');
        Route::delete('questions/{id}/reponse-acceptee', [AcceptedAnswerController::class, 'destroy'])->whereUlid('id')->name('accepted-answer.destroy');

        Route::put('reponses/{id}/vote-utile', [AnswerVoteController::class, 'store'])->whereUlid('id')->name('answer-votes.store');
        Route::delete('reponses/{id}/vote-utile', [AnswerVoteController::class, 'destroy'])->whereUlid('id')->name('answer-votes.destroy');

        Route::post('questions/{id}/signalements', [ReportController::class, 'question'])->whereUlid('id')->name('reports.question');
        Route::post('reponses/{id}/signalements', [ReportController::class, 'answer'])->whereUlid('id')->name('reports.answer');
    });

    // Notifications du compte (réponse reçue, réponse acceptée) : session du navigateur uniquement.
    Route::middleware('abilities:'.TokenAbility::CommunityParticipate->value)->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/lues', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/lue', [NotificationController::class, 'markAsRead'])->whereUuid('id')->name('notifications.read');
    });

    // Administration : rôle administrateur et jeton de session obligatoires.
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('signalements', [AdminReportController::class, 'index'])->name('admin.reports.index');
        Route::post('signalements/{id}/decision', [AdminReportController::class, 'resolve'])->whereUlid('id')->name('admin.reports.resolve');
    });

    // Lecture des projets, des déploiements et des opérations (humain ou IA).
    Route::middleware('abilities:'.TokenAbility::ProjectsRead->value)->group(function () use ($projectName) {
        Route::get('projets', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projets/{projet}', [ProjectController::class, 'show'])
            ->where('projet', $projectName)
            ->name('projects.show');
        Route::get('projets/{projet}/deploiements', [DeploymentController::class, 'index'])
            ->where('projet', $projectName)
            ->name('deployments.index');
        Route::get('operations/{id}', [OperationController::class, 'show'])
            ->whereUlid('id')
            ->name('operations.show');
    });

    // Écriture sur les projets (humain ou IA).
    Route::middleware('abilities:'.TokenAbility::ProjectsWrite->value)->group(function () use ($projectName) {
        Route::post('projets/{projet}/deploiements', [DeploymentController::class, 'store'])
            ->where('projet', $projectName)
            ->name('deployments.store');
    });
});
