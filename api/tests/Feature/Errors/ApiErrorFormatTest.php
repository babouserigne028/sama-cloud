<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\ErrorCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\FakeBusinessException;

/*
 * Ces tests vérifient le contrat d'erreur de l'API : { code, message, details? }.
 * Ils créent de petites routes d'essai qui provoquent chaque type d'erreur.
 */

beforeEach(function () {
    Route::middleware('api')->prefix('api/_test')->group(function () {
        Route::get('ok', fn () => ['ok' => true]);

        Route::post('validation', fn (Request $request) => $request->validate([
            'nom' => ['required', 'string'],
            'taille' => ['required', 'in:petite,moyenne'],
        ]));

        Route::get('protege', fn () => ['ok' => true])->middleware('auth:sanctum');

        Route::get('interdit', fn () => abort(403, 'Détail technique à ne pas montrer'));

        Route::get('modele-absent', fn () => User::query()->findOrFail(999_999));

        Route::get('metier/{category}', fn (string $category) => throw new FakeBusinessException(
            ErrorCategory::from($category),
            'Le quota de projets est atteint.',
            ['limite' => 3],
        ));

        Route::get('metier-sans-details', fn () => throw new FakeBusinessException(ErrorCategory::Conflict));

        Route::get('panne', fn () => throw new RuntimeException('mot de passe de la base : secret'));
    });
});

test('une erreur de validation liste chaque champ fautif en français', function () {
    $response = $this->postJson('/api/_test/validation', ['taille' => 'enorme']);

    $response->assertStatus(422)->assertExactJson([
        'code' => 'validation_echouee',
        'message' => 'Les données envoyées contiennent 2 erreurs.',
        'details' => [
            ['champ' => 'nom', 'message' => 'Le champ nom est obligatoire.'],
            ['champ' => 'taille', 'message' => 'Le champ taille est invalide.'],
        ],
    ]);
});

test('le message de validation est au singulier pour une seule erreur', function () {
    $response = $this->postJson('/api/_test/validation', ['nom' => 'mon-blog', 'taille' => 'enorme']);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Les données envoyées contiennent 1 erreur.')
        ->assertJsonCount(1, 'details');
});

test('une route protégée sans jeton renvoie 401 en JSON, même sans en-tête Accept', function () {
    // Appel volontairement « nu » : get() et non getJson().
    $response = $this->get('/api/_test/protege');

    $response->assertStatus(401)->assertExactJson([
        'code' => 'non_authentifie',
        'message' => 'Authentification requise : jeton absent, expiré ou révoqué.',
    ]);
});

test('un accès refusé renvoie 403 sans révéler le message technique', function () {
    $response = $this->getJson('/api/_test/interdit');

    $response->assertStatus(403)->assertExactJson([
        'code' => 'acces_refuse',
        'message' => "Vous n'avez pas le droit d'effectuer cette action.",
    ]);
});

test('un enregistrement absent renvoie 404 sans révéler le nom du modèle', function () {
    $response = $this->getJson('/api/_test/modele-absent');

    $response->assertStatus(404)->assertExactJson([
        'code' => 'introuvable',
        'message' => 'La ressource demandée est introuvable.',
    ]);

    expect($response->getContent())->not->toContain('Models');
});

test('une adresse inconnue de l\'API renvoie 404 au format commun', function () {
    $this->getJson('/api/adresse-qui-n-existe-pas')
        ->assertStatus(404)
        ->assertJsonPath('code', 'introuvable');
});

test('une mauvaise méthode HTTP renvoie 405 au format commun', function () {
    $this->postJson('/api/_test/ok')
        ->assertStatus(405)
        ->assertJsonPath('code', 'methode_non_autorisee');
});

test('une erreur métier renvoie son code, son message et ses détails avec le bon statut', function (string $category, int $status) {
    $response = $this->getJson("/api/_test/metier/{$category}");

    $response->assertStatus($status)->assertExactJson([
        'code' => 'erreur_factice',
        'message' => 'Le quota de projets est atteint.',
        'details' => ['limite' => 3],
    ]);
})->with([
    'données invalides' => ['invalid', 422],
    'action interdite' => ['forbidden', 403],
    'ressource absente' => ['not_found', 404],
    'conflit d\'état' => ['conflict', 409],
]);

test('la clé « details » est absente quand il n\'y a rien à préciser', function () {
    $response = $this->getJson('/api/_test/metier-sans-details');

    $response->assertStatus(409);

    expect($response->json())->not->toHaveKey('details');
});

test('une erreur métier n\'est pas écrite dans les journaux', function () {
    Exceptions::fake();

    $this->getJson('/api/_test/metier/conflict')->assertStatus(409);

    Exceptions::assertNotReported(FakeBusinessException::class);
});

test('une panne imprévue renvoie 500 sans fuite d\'information, et elle est journalisée', function () {
    config()->set('app.debug', false);
    Exceptions::fake();

    $response = $this->getJson('/api/_test/panne');

    $response->assertStatus(500)->assertExactJson([
        'code' => 'erreur_interne',
        'message' => 'Une erreur interne est survenue. Réessayez dans quelques instants.',
    ]);

    Exceptions::assertReported(RuntimeException::class);
});

test('en mode débogage, une panne imprévue donne le détail technique au développeur', function () {
    config()->set('app.debug', true);
    Exceptions::fake();

    $response = $this->getJson('/api/_test/panne');

    $response->assertStatus(500)
        ->assertJsonPath('code', 'erreur_interne')
        ->assertJsonPath('details.exception', RuntimeException::class)
        ->assertJsonPath('details.message', 'mot de passe de la base : secret');
});

test('une page hors API garde le rendu habituel de Laravel', function () {
    $response = $this->get('/page-web-inconnue');

    $response->assertStatus(404);

    expect($response->headers->get('Content-Type'))->toContain('text/html');
});
