<?php

declare(strict_types=1);

use App\Application\Account\Actions\IssueAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Tests « Feature » : ils démarrent l'application complète et utilisent la base
 * PostgreSQL de test. Chaque test tourne dans une transaction annulée à la fin,
 * donc aucun test ne laisse de données derrière lui.
 *
 * Les tests « Unit » et « Architecture » n'ont pas besoin de l'application.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Aides partagées par les tests. Elles créent de VRAIS jetons, comme le ferait
 * l'API : les tests passent donc par toute la chaîne d'authentification.
 */

/**
 * Jeton de session (humain, back-office) pour ce compte.
 */
function sessionTokenFor(User $user): string
{
    return app(IssueAccessToken::class)->forSession($user, 'Tests')->plainTextToken;
}

/**
 * Jeton d'agent IA (serveur MCP) pour ce compte.
 */
function agentTokenFor(User $user, ?int $monthlySpendingCapFcfa = null): string
{
    return app(IssueAccessToken::class)
        ->forAgent($user, 'Agent de test', 90, $monthlySpendingCapFcfa)
        ->plainTextToken;
}

/**
 * À appeler entre deux requêtes d'un même test quand le jeton change ou est révoqué.
 * Dans les tests, Laravel garde en mémoire l'utilisateur de la requête précédente ;
 * en production, chaque requête repart de zéro.
 */
function forgetAuthenticatedUser(): void
{
    app('auth')->forgetGuards();
}
