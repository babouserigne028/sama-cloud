<?php

declare(strict_types=1);

/*
 * Tests d'architecture : ils vérifient automatiquement que les couches de la
 * clean architecture respectent leurs frontières. Si quelqu'un importe Laravel
 * dans le domaine, ces tests échouent.
 *
 *   Http ─────────► Application ─────────► Domain ◄───────── Infrastructure
 *   (contrôleurs)   (cas d'usage)          (règles métier)   (Eloquent, services externes)
 */

arch('le domaine ne dépend ni de Laravel ni des autres couches')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate',
        'Laravel',
        'App\Application',
        'App\Infrastructure',
        'App\Http',
        'App\Models',
        'App\Providers',
    ]);

arch('les cas d\'usage ne dépendent ni de HTTP ni de l\'infrastructure')
    ->expect('App\Application')
    ->not->toUse([
        'App\Http',
        'App\Infrastructure',
        'Illuminate\Http',
    ]);

arch('l\'infrastructure ne dépend pas de la couche HTTP')
    ->expect('App\Infrastructure')
    ->not->toUse('App\Http');

arch('tout le code applicatif déclare les types stricts')
    ->expect('App')
    ->toUseStrictTypes();

arch('aucune fonction de débogage ne reste dans le code')
    ->preset()
    ->php();

arch('aucune fonction dangereuse (eval, md5, exec…) n\'est utilisée')
    ->preset()
    ->security();
