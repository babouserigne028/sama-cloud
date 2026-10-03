<?php

declare(strict_types=1);

use App\Models\PersonalAccessToken;
use App\Models\User;

/*
 * GET /api/moi : le serveur MCP l'appelle au démarrage pour vérifier son jeton.
 */

test('avec un jeton de session, /api/moi décrit le compte et un jeton humain', function () {
    $user = User::factory()->create(['name' => 'Awa Diop', 'country_code' => 'SN']);

    $response = $this->withToken(sessionTokenFor($user))->getJson('/api/moi');

    $response->assertOk()
        ->assertJsonPath('data.compte.id', $user->id)
        ->assertJsonPath('data.compte.nom', 'Awa Diop')
        ->assertJsonPath('data.jeton.type', 'session')
        ->assertJsonPath('data.jeton.capacites', [
            'projets:lire', 'projets:ecrire', 'projets:supprimer', 'echange:publier', 'communaute:participer', 'profil:modifier', 'jetons:gerer',
        ])
        ->assertJsonMissingPath('data.jeton.valeur')
        ->assertJsonMissingPath('data.jeton.token');
});

test('avec un jeton IA, /api/moi annonce le type agent_ia, ses capacités et son plafond', function () {
    $user = User::factory()->create();

    $response = $this->withToken(agentTokenFor($user, monthlySpendingCapFcfa: 20_000))->getJson('/api/moi');

    $response->assertOk()
        ->assertJsonPath('data.jeton.type', 'agent_ia')
        ->assertJsonPath('data.jeton.plafond_mensuel_fcfa', 20_000)
        ->assertJsonPath('data.jeton.capacites', [
            'projets:lire', 'projets:ecrire', 'projets:supprimer', 'echange:publier',
        ]);
});

test('sans jeton, ou avec un jeton inconnu, l\'accès est refusé', function (?string $token) {
    $request = $token === null ? $this : $this->withToken($token);

    $request->getJson('/api/moi')
        ->assertStatus(401)
        ->assertJsonPath('code', 'non_authentifie');
})->with([
    'aucun jeton' => [null],
    'jeton inventé' => ['sc_live_'.str_repeat('a', 48)],
    'jeton vide de sens' => ['n-importe-quoi'],
]);

test('un jeton expiré est refusé', function () {
    $user = User::factory()->create();
    $token = sessionTokenFor($user);

    $this->travel(8)->days();

    $this->withToken($token)->getJson('/api/moi')
        ->assertStatus(401)
        ->assertJsonPath('code', 'non_authentifie');
});

test('un jeton valide la veille de son expiration est encore accepté', function () {
    $user = User::factory()->create();
    $token = sessionTokenFor($user);

    $this->travel(6)->days();

    $this->withToken($token)->getJson('/api/moi')->assertOk();
});

test('un compte suspendu est bloqué immédiatement, même avec un jeton encore valide', function (string $kind) {
    $user = User::factory()->create();
    $token = $kind === 'session' ? sessionTokenFor($user) : agentTokenFor($user);

    $user->forceFill(['suspended_at' => now()])->save();

    $this->withToken($token)->getJson('/api/moi')
        ->assertStatus(403)
        ->assertJsonPath('code', 'compte_suspendu');
})->with(['session', 'agent_ia']);

test('la date de dernière utilisation du jeton est mise à jour', function () {
    $user = User::factory()->create();
    $token = sessionTokenFor($user);

    expect(PersonalAccessToken::query()->sole()->last_used_at)->toBeNull();

    $this->withToken($token)->getJson('/api/moi')->assertOk();

    expect(PersonalAccessToken::query()->sole()->last_used_at)->not->toBeNull();
});

test('la déconnexion révoque le jeton de session utilisé, et seulement celui-là', function () {
    $user = User::factory()->create();
    $firstSession = sessionTokenFor($user);
    $secondSession = sessionTokenFor($user);

    $this->withToken($firstSession)->postJson('/api/deconnexion')->assertNoContent();

    forgetAuthenticatedUser();
    $this->withToken($firstSession)->getJson('/api/moi')->assertStatus(401);

    forgetAuthenticatedUser();
    $this->withToken($secondSession)->getJson('/api/moi')->assertOk();
});

test('chaque jeton a son propre compteur de limite de débit', function () {
    config()->set('samacloud.api.requests_per_minute', 2);
    $user = User::factory()->create();
    $firstToken = sessionTokenFor($user);
    $secondToken = agentTokenFor($user);

    $this->withToken($firstToken)->getJson('/api/moi')->assertOk();
    $this->withToken($firstToken)->getJson('/api/moi')->assertOk();
    $this->withToken($firstToken)->getJson('/api/moi')->assertStatus(429);

    // L'IA en boucle n'empêche pas l'humain de travailler, et inversement.
    forgetAuthenticatedUser();
    $this->withToken($secondToken)->getJson('/api/moi')->assertOk();
});
