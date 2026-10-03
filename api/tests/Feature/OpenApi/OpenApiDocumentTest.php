<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * La spécification OpenAPI est générée à partir du code. Ces tests garantissent
 * qu'elle reste fidèle à l'API et que le fichier partagé avec l'équipe est à jour.
 */

/**
 * Document OpenAPI généré à partir du code actuel.
 * La génération analyse tout le code : on ne la fait qu'une fois pour tous les tests du fichier.
 *
 * @return array<string, mixed>
 */
function generatedOpenApiDocument(): array
{
    static $document = null;

    // Aller-retour par JSON : le document a exactement la forme qu'il aura une fois écrit dans le fichier.
    return $document ??= json_decode(
        (string) json_encode(app(Generator::class)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API))),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * Toutes les opérations du document, sous la forme « METHODE /chemin » => opération.
 *
 * @param  array<string, mixed>  $document
 * @return array<string, array<string, mixed>>
 */
function openApiOperations(array $document): array
{
    $operations = [];

    foreach ($document['paths'] as $path => $methods) {
        foreach ($methods as $method => $operation) {
            if (is_array($operation) && isset($operation['responses'])) {
                $operations[strtoupper($method).' '.$path] = $operation;
            }
        }
    }

    return $operations;
}

test('le fichier openapi.json partagé avec l\'équipe est à jour', function () {
    $committed = json_decode((string) file_get_contents(base_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($committed)->toEqual(
        generatedOpenApiDocument(),
        'openapi.json n\'est plus à jour : lancez « composer openapi » puis commitez le fichier.',
    );
});

test('toutes les routes de l\'API figurent dans la spécification', function () {
    $documented = array_keys(openApiOperations(generatedOpenApiDocument()));

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/'))
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' /'.substr($route->uri(), 4)))
        ->values()
        ->all();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($documented)->toContain($route);
    }
});

test('chaque réponse d\'erreur utilise le format commun et annonce son code', function () {
    $document = generatedOpenApiDocument();

    expect($document['components']['schemas']['Erreur']['required'])->toBe(['code', 'message']);

    foreach (openApiOperations($document) as $name => $operation) {
        foreach ($operation['responses'] as $status => $response) {
            if ((int) $status < 400) {
                continue;
            }

            expect($response['content']['application/json']['schema'])
                ->toBe(['$ref' => '#/components/schemas/Erreur'], "{$name} → {$status}");

            expect($response['description'])->toMatch('/^- `[a-z_]+` — /', "{$name} → {$status}");
        }
    }
});

test('toutes les routes annoncent la limite de débit', function () {
    foreach (openApiOperations(generatedOpenApiDocument()) as $name => $operation) {
        expect(array_key_exists('429', $operation['responses']))->toBeTrue($name);
    }
});

test('seules l\'inscription, la connexion et la lecture de la communauté sont publiques', function () {
    $document = generatedOpenApiDocument();

    expect($document['security'])->toBe([['jeton' => []]])
        ->and($document['components']['securitySchemes']['jeton']['scheme'])->toBe('bearer');

    $publicOperations = [];

    foreach (openApiOperations($document) as $name => $operation) {
        if (($operation['security'] ?? null) === []) {
            $publicOperations[] = $name;

            continue;
        }

        // Route protégée : elle hérite de la sécurité globale et documente 401 et 403.
        expect(array_key_exists('security', $operation))->toBeFalse($name)
            ->and(array_key_exists('401', $operation['responses']))->toBeTrue($name)
            ->and(array_key_exists('403', $operation['responses']))->toBeTrue($name);
    }

    expect($publicOperations)->toEqualCanonicalizing([
        'POST /inscription',
        'POST /connexion',
        'GET /technologies',
        'GET /profils',
        'GET /profils/{pseudo}',
        'GET /classement',
        'GET /questions',
        'GET /questions/{id}',
        'GET /questions/{id}/reponses',
    ]);
});

test('les erreurs métier déclarées sur une action apparaissent dans la spécification', function () {
    $operations = openApiOperations(generatedOpenApiDocument());

    expect($operations['POST /jetons-ia']['responses']['409']['description'])->toContain('limite_jetons_atteinte')
        ->and($operations['POST /jetons-ia']['responses']['403']['description'])->toContain('capacite_manquante')
        ->and($operations['POST /connexion']['responses']['401']['description'])->toContain('identifiants_invalides')
        ->and($operations['DELETE /jetons-ia/{id}']['responses']['404']['description'])->toContain('introuvable');
});

test('la spécification ne dépend pas de la machine qui la génère', function () {
    expect(generatedOpenApiDocument()['servers'])->toBe([
        ['url' => '/api', 'description' => 'Serveur qui héberge cette documentation'],
    ]);
});

test('la documentation est consultable dans un navigateur', function () {
    $this->get('/docs/api')->assertOk();
    $this->getJson('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'SamaCloud');
});
