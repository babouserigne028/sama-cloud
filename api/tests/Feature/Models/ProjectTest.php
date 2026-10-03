<?php

declare(strict_types=1);

use App\Domain\Project\Enums\ProjectStatus;
use App\Models\Deployment;
use App\Models\EnvironmentVariable;
use App\Models\Operation;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectDatabase;
use App\Models\ProjectService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
 * Règles garanties par la base elle-même pour les projets.
 *
 * Quand une requête est censée échouer, elle est lancée dans DB::transaction() :
 * PostgreSQL annule alors seulement cette requête et le test peut continuer.
 */

test('un développeur ne peut pas avoir deux projets du même nom', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);

    expect(fn () => DB::transaction(
        fn () => Project::factory()->for($user)->create(['name' => 'mon-blog', 'slug' => 'mon-blog-4f2a']),
    ))->toThrow(UniqueConstraintViolationException::class);
});

test('deux développeurs peuvent choisir le même nom, avec des sous-domaines différents', function () {
    Project::factory()->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);
    Project::factory()->create(['name' => 'mon-blog', 'slug' => 'mon-blog-4f2a']);

    expect(Project::query()->where('name', 'mon-blog')->count())->toBe(2);
});

test('le sous-domaine est unique sur toute la plateforme', function () {
    Project::factory()->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);

    expect(fn () => DB::transaction(
        fn () => Project::factory()->create(['name' => 'autre-nom', 'slug' => 'mon-blog']),
    ))->toThrow(UniqueConstraintViolationException::class);
});

test('le nom et le sous-domaine redeviennent libres après la suppression du projet', function () {
    $user = User::factory()->create();
    $deleted = Project::factory()->for($user)->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);
    $deleted->delete();

    $recreated = Project::factory()->for($user)->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);

    expect($recreated->exists)->toBeTrue()
        ->and(Project::query()->count())->toBe(1)
        ->and(Project::withTrashed()->count())->toBe(2);
});

test('la base refuse un nom de projet qui ne respecte pas le format de datacloud.yaml', function (string $invalidName) {
    expect(fn () => DB::transaction(
        fn () => Project::factory()->create(['name' => $invalidName, 'slug' => 'slug-valide']),
    ))->toThrow(QueryException::class);
})->with([
    'majuscules' => ['Mon-Blog'],
    'commence par un chiffre' => ['1blog'],
    'trop court' => ['ab'],
    'finit par un tiret' => ['mon-blog-'],
    'caractère interdit' => ['mon_blog'],
    'plus de 30 caractères' => [str_repeat('a', 31)],
]);

test('le statut et la configuration sont relus avec leur vrai type', function () {
    $project = Project::factory()->pendingPayment()->create()->fresh();

    expect($project->status)->toBe(ProjectStatus::PendingPayment)
        ->and($project->config)->toBe(['version' => 1, 'services' => ['web' => ['framework' => 'laravel']]])
        ->and($project->paid_until)->toBeNull();
});

test('effacer définitivement un projet efface ses ressources mais garde la trace des commandes et des opérations', function () {
    $project = Project::factory()->create();
    ProjectService::factory()->for($project)->create();
    ProjectDatabase::factory()->for($project)->create();
    EnvironmentVariable::factory()->for($project)->create();
    $deployment = Deployment::factory()->for($project)->create();
    $order = Order::factory()->for($project)->create();

    // Le déploiement doit partir avant son opération de suivi : la base l'impose.
    $project->forceDelete();

    expect(ProjectService::query()->count())->toBe(0)
        ->and(ProjectDatabase::query()->count())->toBe(0)
        ->and(EnvironmentVariable::query()->count())->toBe(0)
        ->and(Deployment::query()->count())->toBe(0)
        ->and($order->fresh()?->project_id)->toBeNull()
        ->and(Operation::query()->findOrFail($deployment->operation_id)->project_id)->toBeNull();
});

test('un compte qui possède des projets ne peut pas être effacé', function () {
    $project = Project::factory()->create();

    expect(fn () => DB::transaction(fn () => $project->user->delete()))
        ->toThrow(QueryException::class);
});

test('un projet donne accès à ses services, bases, variables et déploiements', function () {
    $project = Project::factory()->create();
    ProjectService::factory()->for($project)->create(['name' => 'web']);
    ProjectService::factory()->for($project)->worker()->create();
    ProjectDatabase::factory()->for($project)->create();
    EnvironmentVariable::factory()->for($project)->create();
    Deployment::factory()->for($project)->create();

    $project->load(['services', 'databases', 'environmentVariables', 'deployments', 'operations', 'user']);

    expect($project->services)->toHaveCount(2)
        ->and($project->databases)->toHaveCount(1)
        ->and($project->environmentVariables)->toHaveCount(1)
        ->and($project->deployments)->toHaveCount(1)
        ->and($project->operations)->toHaveCount(1)
        ->and($project->user->projects()->count())->toBe(1);
});

test('un projet ne peut pas avoir deux services ou deux bases du même nom', function () {
    $project = Project::factory()->create();
    ProjectService::factory()->for($project)->create(['name' => 'web']);
    ProjectDatabase::factory()->for($project)->create(['name' => 'principale']);

    expect(fn () => DB::transaction(
        fn () => ProjectService::factory()->for($project)->create(['name' => 'web']),
    ))->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => DB::transaction(
        fn () => ProjectDatabase::factory()->for($project)->create(['name' => 'principale']),
    ))->toThrow(UniqueConstraintViolationException::class);
});
