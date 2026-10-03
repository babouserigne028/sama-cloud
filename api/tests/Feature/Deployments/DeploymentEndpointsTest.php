<?php

declare(strict_types=1);

use App\Application\Deployment\Events\DeploymentRequested;
use App\Domain\Deployment\Enums\DeploymentStatus;
use App\Domain\Deployment\Enums\OperationStatus;
use App\Domain\Deployment\Enums\OperationType;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Shared\Enums\ActorType;
use App\Models\Deployment;
use App\Models\Operation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
 * Déploiements d'un projet (outils MCP lister_deploiements et redeployer).
 */

beforeEach(function () {
    Event::fake([DeploymentRequested::class]);

    $this->user = User::factory()->create();
    $this->token = sessionTokenFor($this->user);
    $this->project = Project::factory()->for($this->user)->create([
        'name' => 'mon-blog',
        'slug' => 'mon-blog',
        'branch' => 'main',
        'config' => ['version' => 1, 'services' => ['web' => ['framework' => 'laravel']]],
    ]);
});

test('lancer un déploiement crée le déploiement et son opération, et prévient le moteur', function () {
    $response = $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements');

    $deployment = Deployment::query()->sole();
    $operation = Operation::query()->sole();

    $response->assertStatus(202)->assertJson(['data' => [
        'id' => $deployment->id,
        'statut' => 'en_attente',
        'acteur' => 'humain',
        'branche' => 'main',
        'commit' => null,
        'operation_id' => $operation->id,
    ]]);

    expect($deployment->status)->toBe(DeploymentStatus::Queued)
        ->and($deployment->user_id)->toBe($this->user->id)
        ->and($deployment->project_id)->toBe($this->project->id)
        // La configuration du projet est copiée dans le déploiement.
        ->and($deployment->config)->toBe(['version' => 1, 'services' => ['web' => ['framework' => 'laravel']]])
        ->and($operation->type)->toBe(OperationType::Deployment)
        ->and($operation->status)->toBe(OperationStatus::Pending)
        ->and($operation->progress)->toBe(0)
        ->and($operation->user_id)->toBe($this->user->id);

    Event::assertDispatchedTimes(DeploymentRequested::class, 1);
    Event::assertDispatched(
        DeploymentRequested::class,
        fn (DeploymentRequested $event) => $event->deploymentId === $deployment->id
            && $event->operationId === $operation->id
            && $event->projectId === $this->project->id,
    );
});

test('un déploiement lancé avec un jeton IA est marqué « ia », quoi que dise le client', function () {
    $this->withToken(agentTokenFor($this->user))
        ->postJson('/api/projets/mon-blog/deploiements', ['acteur' => 'humain', 'actor' => 'humain'])
        ->assertStatus(202)
        ->assertJsonPath('data.acteur', 'ia');

    expect(Deployment::query()->sole()->actor)->toBe(ActorType::Ai);
});

test('la branche et le commit peuvent être précisés', function () {
    $this->withToken($this->token)
        ->postJson('/api/projets/mon-blog/deploiements', ['branche' => 'feature/paiement-wave', 'commit' => 'a1b2c3d4e5'])
        ->assertStatus(202)
        ->assertJsonPath('data.branche', 'feature/paiement-wave')
        ->assertJsonPath('data.commit', 'a1b2c3d4e5');
});

test('une branche ou un commit dangereux pour git est refusé', function (array $payload, string $invalidField) {
    $response = $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements', $payload);

    $response->assertStatus(422)->assertJsonPath('details.0.champ', $invalidField);

    expect(Deployment::query()->count())->toBe(0);
    Event::assertNotDispatched(DeploymentRequested::class);
})->with([
    'option de commande' => [['branche' => '--upload-pack=malveillant'], 'branche'],
    'point-virgule' => [['branche' => 'main; rm -rf /'], 'branche'],
    'espace' => [['branche' => 'main autre'], 'branche'],
    'remontée de dossier' => [['branche' => 'a/../../b'], 'branche'],
    'commit non hexadécimal' => [['commit' => 'HEAD~1'], 'commit'],
    'commit trop court' => [['commit' => 'abc12'], 'commit'],
    'commit en majuscules' => [['commit' => 'A1B2C3D4'], 'commit'],
]);

test('un second déploiement est refusé tant que le premier n\'est pas terminé', function (DeploymentStatus $status) {
    $running = Deployment::factory()->for($this->project)->create(['status' => $status]);

    $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements')
        ->assertStatus(409)
        ->assertExactJson([
            'code' => 'deploiement_en_cours',
            'message' => "Un déploiement est déjà en cours pour ce projet. Attendez sa fin avant d'en lancer un autre.",
            'details' => ['deploiement_id' => $running->id, 'operation_id' => $running->operation_id],
        ]);

    expect(Deployment::query()->count())->toBe(1);
    Event::assertNotDispatched(DeploymentRequested::class);
})->with([
    'en attente' => [DeploymentStatus::Queued],
    'construction' => [DeploymentStatus::Building],
    'publication' => [DeploymentStatus::Releasing],
]);

test('on peut redéployer après un déploiement terminé, réussi ou échoué', function (DeploymentStatus $status) {
    Deployment::factory()->for($this->project)->create(['status' => $status]);

    $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements')->assertStatus(202);

    expect(Deployment::query()->count())->toBe(2);
})->with([
    'en ligne' => [DeploymentStatus::Live],
    'échec' => [DeploymentStatus::Failed],
]);

test('le déploiement en cours d\'un autre projet ne bloque pas celui-ci', function () {
    Deployment::factory()->create(['status' => DeploymentStatus::Building]);

    $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements')->assertStatus(202);
});

test('un projet qui n\'est ni en ligne ni en échec ne peut pas être déployé', function (ProjectStatus $status) {
    $this->project->update(['status' => $status]);

    $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements')
        ->assertStatus(409)
        ->assertJsonPath('code', 'projet_non_deployable')
        ->assertJsonPath('details.statut', $status->value);

    expect(Deployment::query()->count())->toBe(0);
    Event::assertNotDispatched(DeploymentRequested::class);
})->with([
    'en attente de paiement' => [ProjectStatus::PendingPayment],
    'provisionnement' => [ProjectStatus::Provisioning],
    'arrêté' => [ProjectStatus::Stopped],
    'en suppression' => [ProjectStatus::Deleting],
]);

test('un projet en échec peut être redéployé sans nouveau paiement', function () {
    $this->project->update(['status' => ProjectStatus::Failed]);

    $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements')->assertStatus(202);
});

test('le déploiement est refusé quand la période payée est terminée ou absente', function (?string $paidUntil) {
    $this->project->update(['paid_until' => $paidUntil]);

    $this->withToken($this->token)->postJson('/api/projets/mon-blog/deploiements')
        ->assertStatus(409)
        ->assertJsonPath('code', 'periode_expiree');

    expect(Deployment::query()->count())->toBe(0);
})->with([
    'échéance passée d\'une seconde' => [fn () => now()->subSecond()->toDateTimeString()],
    'aucune échéance' => [null],
]);

test('on ne peut pas déployer le projet d\'un autre compte', function () {
    Project::factory()->create(['name' => 'projet-voisin', 'slug' => 'projet-voisin']);

    $this->withToken($this->token)->postJson('/api/projets/projet-voisin/deploiements')->assertStatus(404);

    expect(Deployment::query()->count())->toBe(0);
});

test('l\'historique liste les déploiements du projet, du plus récent au plus ancien', function () {
    $this->freezeTime();

    $old = Deployment::factory()->for($this->project)->live()->create([
        'created_at' => now()->subDay(),
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay()->addSeconds(95),
        'commit_sha' => 'a1b2c3d',
    ]);
    $recent = Deployment::factory()->for($this->project)->byAi()->failed()->create();
    Deployment::factory()->create();

    $response = $this->withToken($this->token)->getJson('/api/projets/mon-blog/deploiements');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $recent->id)
        ->assertJsonPath('data.0.acteur', 'ia')
        ->assertJsonPath('data.0.statut', 'echec')
        ->assertJsonPath('data.0.raison_echec', 'La commande de build a échoué.')
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.1.statut', 'en_ligne')
        ->assertJsonPath('data.1.commit', 'a1b2c3d')
        ->assertJsonPath('data.1.duree_secondes', 95)
        ->assertJsonPath('meta.total', 2);

    // La configuration interne du déploiement n'est pas exposée.
    expect($response->json('data.0'))->not->toHaveKey('config');
});

test('l\'historique est paginé par 20', function () {
    Deployment::factory()->for($this->project)->live()->count(21)->create();

    $this->withToken($this->token)->getJson('/api/projets/mon-blog/deploiements')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 21)
        ->assertJsonPath('meta.last_page', 2);

    $this->withToken($this->token)->getJson('/api/projets/mon-blog/deploiements?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('l\'historique du projet d\'un autre compte est introuvable', function () {
    $other = Project::factory()->create(['name' => 'projet-voisin', 'slug' => 'projet-voisin']);
    Deployment::factory()->for($other)->create();

    $this->withToken($this->token)->getJson('/api/projets/projet-voisin/deploiements')->assertStatus(404);
});

test('les routes de déploiement exigent un jeton', function () {
    $this->getJson('/api/projets/mon-blog/deploiements')->assertStatus(401);
    $this->postJson('/api/projets/mon-blog/deploiements')->assertStatus(401);

    expect(Deployment::query()->count())->toBe(0);
});
