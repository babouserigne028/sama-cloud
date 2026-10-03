<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Limite de débit de l'API. La limite réelle est de 60 requêtes par minute ;
 * les tests la baissent à 3 pour rester rapides.
 */

beforeEach(function () {
    config()->set('samacloud.api.requests_per_minute', 3);

    Route::middleware('api')->get('api/_test/ok', fn () => ['ok' => true]);
});

test('les requêtes sous la limite passent et annoncent le quota restant', function () {
    $response = $this->getJson('/api/_test/ok');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', '3')
        ->assertHeader('X-RateLimit-Remaining', '2');
});

test('la requête qui dépasse la limite est refusée avec 429 au format commun', function () {
    foreach (range(1, 3) as $attempt) {
        $this->getJson('/api/_test/ok')->assertOk();
    }

    $response = $this->getJson('/api/_test/ok');

    $response->assertStatus(429)
        ->assertJsonPath('code', 'trop_de_requetes')
        ->assertJsonPath('message', 'Trop de requêtes. Patientez avant de réessayer.')
        ->assertHeader('Retry-After');

    // Le délai d'attente annoncé dans le corps est celui de l'en-tête Retry-After.
    expect($response->json('details.reessayer_dans_secondes'))
        ->toBeInt()
        ->toBe((int) $response->headers->get('Retry-After'))
        ->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(60);
});

test('chaque adresse IP a son propre compteur', function () {
    foreach (range(1, 3) as $attempt) {
        $this->getJson('/api/_test/ok')->assertOk();
    }

    $this->getJson('/api/_test/ok')->assertStatus(429);

    // Un autre visiteur n'est pas pénalisé par le premier.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/_test/ok')
        ->assertOk();
});

test('les appels sans jeton sur une route protégée sont comptés et finissent bloqués', function () {
    foreach (range(1, 3) as $attempt) {
        $this->getJson('/api/moi')->assertStatus(401);
    }

    $this->getJson('/api/moi')->assertStatus(429)->assertJsonPath('code', 'trop_de_requetes');
});

test('essayer des jetons au hasard est plafonné par adresse IP', function () {
    config()->set('samacloud.api.requests_per_minute_per_ip', 4);

    foreach (range(1, 4) as $attempt) {
        forgetAuthenticatedUser();
        $this->withToken('sc_live_jeton-invente-numero-'.$attempt)->getJson('/api/moi')->assertStatus(401);
    }

    forgetAuthenticatedUser();

    // Un nouveau jeton inventé ne remet pas le compteur à zéro.
    $this->withToken('sc_live_encore-un-autre')->getJson('/api/moi')->assertStatus(429);
});

test('le plafond par adresse IP ne gêne pas un jeton valide dans les limites', function () {
    config()->set('samacloud.api.requests_per_minute', 3);
    config()->set('samacloud.api.requests_per_minute_per_ip', 10);

    $token = sessionTokenFor(User::factory()->create());

    foreach (range(1, 3) as $attempt) {
        $this->withToken($token)->getJson('/api/moi')->assertOk();
    }

    $this->withToken($token)->getJson('/api/moi')->assertStatus(429);
});
