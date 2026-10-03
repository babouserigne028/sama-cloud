<?php

declare(strict_types=1);

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
