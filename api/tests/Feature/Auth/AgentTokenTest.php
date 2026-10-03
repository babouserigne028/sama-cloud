<?php

declare(strict_types=1);

use App\Domain\Account\Enums\TokenKind;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * Jetons d'agent IA : créés, listés et révoqués depuis le back-office (jeton de session).
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->session = sessionTokenFor($this->user);
});

test('un développeur crée un jeton IA et reçoit sa valeur une seule fois', function () {
    $this->freezeTime();

    $response = $this->withToken($this->session)->postJson('/api/jetons-ia', [
        'nom' => 'VS Code portable',
        'plafond_mensuel_fcfa' => 15_000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.nom', 'VS Code portable')
        ->assertJsonPath('data.type', 'agent_ia')
        ->assertJsonPath('data.plafond_mensuel_fcfa', 15_000)
        ->assertJsonPath('data.capacites', ['projets:lire', 'projets:ecrire', 'projets:supprimer', 'echange:publier'])
        // 90 jours par défaut.
        ->assertJsonPath('data.expire_le', now()->addDays(90)->toIso8601String());

    $value = $response->json('data.valeur');

    expect($value)->toStartWith('sc_live_')
        ->and(strlen($value))->toBe(56)
        ->and($response->json('data.prefixe'))->toBe(substr($value, 0, 12));
});

test('le jeton IA créé fonctionne et se présente comme agent IA', function () {
    $value = $this->withToken($this->session)->postJson('/api/jetons-ia', ['nom' => 'Cursor'])->json('data.valeur');

    forgetAuthenticatedUser();

    $this->withToken($value)->getJson('/api/moi')
        ->assertOk()
        ->assertJsonPath('data.compte.id', $this->user->id)
        ->assertJsonPath('data.jeton.type', 'agent_ia');
});

test('seule l\'empreinte du jeton est enregistrée en base', function () {
    $value = $this->withToken($this->session)->postJson('/api/jetons-ia', ['nom' => 'Cursor'])->json('data.valeur');

    $stored = DB::table('personal_access_tokens')->where('kind', 'agent_ia')->sole();

    expect($stored->token)->toBe(hash('sha256', $value))
        ->and(json_encode($stored))->not->toContain($value);
});

test('le compte de démonstration reçoit des jetons de test', function () {
    $demo = User::factory()->demo()->create();

    $value = $this->withToken(sessionTokenFor($demo))->postJson('/api/jetons-ia', ['nom' => 'Jury'])->json('data.valeur');

    expect($value)->toStartWith('sc_test_');
});

test('la durée de vie du jeton peut être choisie', function () {
    $this->freezeTime();

    $this->withToken($this->session)->postJson('/api/jetons-ia', ['nom' => 'Court', 'expiration_jours' => 7])
        ->assertCreated()
        ->assertJsonPath('data.expire_le', now()->addDays(7)->toIso8601String())
        ->assertJsonPath('data.plafond_mensuel_fcfa', null);
});

test('les données invalides d\'un jeton IA sont refusées', function (array $payload, string $invalidField) {
    $response = $this->withToken($this->session)->postJson('/api/jetons-ia', $payload);

    $response->assertStatus(422);

    expect(collect($response->json('details'))->pluck('champ')->all())->toBe([$invalidField])
        ->and(PersonalAccessToken::query()->where('kind', TokenKind::Agent)->count())->toBe(0);
})->with([
    'nom absent' => [[], 'nom'],
    'expiration à zéro' => [['nom' => 'Test', 'expiration_jours' => 0], 'expiration_jours'],
    'expiration trop longue' => [['nom' => 'Test', 'expiration_jours' => 366], 'expiration_jours'],
    'plafond négatif' => [['nom' => 'Test', 'plafond_mensuel_fcfa' => -1], 'plafond_mensuel_fcfa'],
    'plafond non entier' => [['nom' => 'Test', 'plafond_mensuel_fcfa' => 'beaucoup'], 'plafond_mensuel_fcfa'],
]);

test('la liste montre les jetons IA du compte, sans leur valeur ni les sessions ni ceux des autres', function () {
    agentTokenFor($this->user);
    agentTokenFor($this->user);
    agentTokenFor(User::factory()->create());

    $response = $this->withToken($this->session)->getJson('/api/jetons-ia');

    $response->assertOk()->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $token) {
        expect($token)->not->toHaveKey('valeur')
            ->and($token['type'])->toBe('agent_ia')
            ->and($token['prefixe'])->toStartWith('sc_live_');
    }
});

test('révoquer un jeton IA le rend inutilisable immédiatement', function () {
    $value = agentTokenFor($this->user);
    $tokenId = PersonalAccessToken::query()->where('kind', TokenKind::Agent)->sole()->id;

    $this->withToken($this->session)->deleteJson("/api/jetons-ia/{$tokenId}")->assertNoContent();

    forgetAuthenticatedUser();

    $this->withToken($value)->getJson('/api/moi')->assertStatus(401);
});

test('on ne peut pas révoquer le jeton IA d\'un autre compte', function () {
    $other = User::factory()->create();
    agentTokenFor($other);
    $otherTokenId = $other->tokens()->sole()->id;

    $this->withToken($this->session)->deleteJson("/api/jetons-ia/{$otherTokenId}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'introuvable');

    expect($other->tokens()->count())->toBe(1);
});

test('cette route ne permet pas de révoquer un jeton de session', function () {
    $sessionId = $this->user->tokens()->where('kind', TokenKind::Session)->sole()->id;

    $this->withToken($this->session)->deleteJson("/api/jetons-ia/{$sessionId}")->assertStatus(404);

    expect($this->user->tokens()->count())->toBe(1);
});

test('un jeton IA ne peut ni créer, ni lister, ni révoquer de jetons, ni déconnecter', function (string $method, string $uri) {
    $agent = agentTokenFor($this->user);
    $tokenId = $this->user->tokens()->where('kind', TokenKind::Agent)->sole()->id;

    $response = $this->withToken($agent)->json($method, str_replace('{id}', (string) $tokenId, $uri), ['nom' => 'Nouveau']);

    $response->assertStatus(403)->assertExactJson([
        'code' => 'capacite_manquante',
        'message' => "Ce jeton n'a pas la capacité nécessaire pour cette action.",
        'details' => ['capacites_requises' => ['jetons:gerer']],
    ]);

    expect($this->user->tokens()->count())->toBe(2);
})->with([
    'créer' => ['POST', '/api/jetons-ia'],
    'lister' => ['GET', '/api/jetons-ia'],
    'révoquer' => ['DELETE', '/api/jetons-ia/{id}'],
    'déconnecter' => ['POST', '/api/deconnexion'],
]);

test('le nombre de jetons IA actifs par compte est limité', function () {
    config()->set('samacloud.auth.max_agent_tokens', 2);
    agentTokenFor($this->user);
    agentTokenFor($this->user);

    $this->withToken($this->session)->postJson('/api/jetons-ia', ['nom' => 'De trop'])
        ->assertStatus(409)
        ->assertExactJson([
            'code' => 'limite_jetons_atteinte',
            'message' => "Vous avez atteint la limite de 2 jetons IA actifs. Révoquez-en un avant d'en créer un nouveau.",
            'details' => ['limite' => 2],
        ]);

    expect($this->user->tokens()->where('kind', TokenKind::Agent)->count())->toBe(2);
});

test('un jeton IA expiré ne compte plus dans la limite, ni les jetons des autres comptes', function () {
    config()->set('samacloud.auth.max_agent_tokens', 1);
    agentTokenFor($this->user);
    agentTokenFor(User::factory()->create());

    // Le jeton IA (90 jours) expire ; on se reconnecte car la session (7 jours) a expiré aussi.
    $this->travel(91)->days();
    $freshSession = sessionTokenFor($this->user);

    $this->withToken($freshSession)->postJson('/api/jetons-ia', ['nom' => 'Remplaçant'])->assertCreated();
});

test('les routes de gestion des jetons exigent d\'être connecté', function () {
    $this->getJson('/api/jetons-ia')->assertStatus(401);
    $this->postJson('/api/jetons-ia', ['nom' => 'Test'])->assertStatus(401);
    $this->deleteJson('/api/jetons-ia/1')->assertStatus(401);
    $this->postJson('/api/deconnexion')->assertStatus(401);
});
