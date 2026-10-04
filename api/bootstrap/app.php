<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\BusinessException;
use App\Http\Errors\ApiErrorRenderer;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Toute l'API répond en JSON, même si le client oublie l'en-tête « Accept ».
        $middleware->api(prepend: [ForceJsonResponse::class]);

        // Laravel retire les espaces au début et à la fin des champs reçus. Il ne doit pas le faire
        // pour le contenu d'un fichier (le saut de ligne final compte) ni pour un mot de passe.
        $middleware->trimStrings(except: ['contenu', 'mot_de_passe', 'mot_de_passe_confirmation']);

        // Limite de débit définie dans AppServiceProvider (limiteur « api »).
        $middleware->throttleApi();

        // Par défaut, Laravel vérifie le jeton avant la limite de débit : un appel refusé (401)
        // n'était donc jamais compté. On place la limite de débit AVANT l'authentification.
        // C'est la liste d'ordre de Laravel, avec ces deux lignes inversées.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesRequests::class,
            AuthenticatesSessions::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

        $middleware->alias([
            // Le jeton doit avoir TOUTES les capacités listées (ex. abilities:projets:ecrire).
            'abilities' => CheckAbilities::class,
            // Le jeton doit avoir AU MOINS UNE des capacités listées.
            'ability' => CheckForAnyAbility::class,
            // Le compte ne doit pas être suspendu.
            'account.active' => EnsureAccountIsActive::class,
            // Le compte doit être administrateur et connecté par le back-office.
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Une erreur métier est prévue (quota atteint, lien expiré…) : inutile de l'écrire dans les journaux.
        $exceptions->dontReport(BusinessException::class);

        // Format d'erreur commun { code, message, details? } pour toute l'API.
        $exceptions->render(
            fn (Throwable $exception, Request $request) => app(ApiErrorRenderer::class)->render($exception, $request),
        );
    })->create();
