<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\BusinessException;
use App\Http\Errors\ApiErrorRenderer;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        // Limite de débit définie dans AppServiceProvider (limiteur « api »).
        $middleware->throttleApi();

        $middleware->alias([
            // Le jeton doit avoir TOUTES les capacités listées (ex. abilities:projets:ecrire).
            'abilities' => CheckAbilities::class,
            // Le jeton doit avoir AU MOINS UNE des capacités listées.
            'ability' => CheckForAnyAbility::class,
            // Le compte ne doit pas être suspendu.
            'account.active' => EnsureAccountIsActive::class,
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
