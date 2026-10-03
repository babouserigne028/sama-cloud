<?php

declare(strict_types=1);

use App\Domain\Project\Enums\VariableOrigin;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\ProjectDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
 * Les secrets (valeurs des variables, identifiants des bases) sont chiffrés en base
 * et ne sortent jamais dans une réponse JSON.
 */

test('la valeur d\'une variable est chiffrée en base et relue en clair par l\'application', function () {
    $variable = EnvironmentVariable::factory()->secret()->create(['value' => 'mot-de-passe-smtp']);

    $storedValue = DB::table('environment_variables')->where('id', $variable->id)->value('value');

    expect($storedValue)->not->toContain('mot-de-passe-smtp')
        ->and($variable->fresh()?->value)->toBe('mot-de-passe-smtp');
});

test('la valeur d\'une variable n\'apparaît jamais dans le JSON du modèle', function () {
    $variable = EnvironmentVariable::factory()->secret()->create(['value' => 'mot-de-passe-smtp']);

    expect($variable->toArray())->not->toHaveKey('value')
        ->and($variable->toJson())->not->toContain('mot-de-passe-smtp');
});

test('un secret déclaré sans valeur est signalé comme manquant', function () {
    $awaiting = EnvironmentVariable::factory()->awaitingValue()->create()->fresh();
    $provided = EnvironmentVariable::factory()->secret()->create()->fresh();

    expect($awaiting?->isMissingValue())->toBeTrue()
        ->and($awaiting?->origin)->toBe(VariableOrigin::Secret)
        ->and($provided?->isMissingValue())->toBeFalse();
});

test('une variable partagée du projet ne peut pas exister en double', function () {
    $project = Project::factory()->create();
    EnvironmentVariable::factory()->for($project)->create(['service_name' => null, 'name' => 'APP_KEY']);

    // Sans « nullsNotDistinct », PostgreSQL accepterait ce doublon car le service est vide.
    expect(fn () => DB::transaction(
        fn () => EnvironmentVariable::factory()->for($project)->create(['service_name' => null, 'name' => 'APP_KEY']),
    ))->toThrow(UniqueConstraintViolationException::class);
});

test('deux services du même projet peuvent avoir une variable du même nom', function () {
    $project = Project::factory()->create();
    EnvironmentVariable::factory()->for($project)->create(['service_name' => 'web', 'name' => 'APP_ENV']);
    EnvironmentVariable::factory()->for($project)->create(['service_name' => 'api', 'name' => 'APP_ENV']);

    expect($project->environmentVariables()->count())->toBe(2);

    expect(fn () => DB::transaction(
        fn () => EnvironmentVariable::factory()->for($project)->create(['service_name' => 'web', 'name' => 'APP_ENV']),
    ))->toThrow(UniqueConstraintViolationException::class);
});

test('la base refuse un nom de variable qui ne respecte pas le format de datacloud.yaml', function (string $invalidName) {
    expect(fn () => DB::transaction(
        fn () => EnvironmentVariable::factory()->create(['name' => $invalidName]),
    ))->toThrow(QueryException::class);
})->with([
    'minuscules' => ['app_env'],
    'commence par un chiffre' => ['1APP'],
    'tiret' => ['APP-ENV'],
]);

test('les identifiants d\'une base sont chiffrés en base et absents du JSON', function () {
    $database = ProjectDatabase::factory()->create([
        'credentials' => ['utilisateur' => 'app', 'mot_de_passe' => 'tres-secret'],
    ]);

    $storedValue = DB::table('project_databases')->where('id', $database->id)->value('credentials');

    expect($storedValue)->not->toContain('tres-secret')
        ->and($database->fresh()?->credentials)->toBe(['utilisateur' => 'app', 'mot_de_passe' => 'tres-secret'])
        ->and($database->toArray())->not->toHaveKey('credentials');
});
