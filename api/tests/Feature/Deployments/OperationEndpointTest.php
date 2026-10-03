<?php

declare(strict_types=1);

use App\Domain\Deployment\Enums\OperationStatus;
use App\Models\Deployment;
use App\Models\Operation;
use App\Models\Project;
use App\Models\User;

/*
 * GET /api/operations/{id} (outil MCP suivre_operation).
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = sessionTokenFor($this->user);
    $this->project = Project::factory()->for($this->user)->create();
});

test('une opération en cours donne son statut, son étape et son avancement', function () {
    $deployment = Deployment::factory()->for($this->project)->create();
    $deployment->operation->update([
        'status' => OperationStatus::Running,
        'step' => 'Construction de l\'image',
        'progress' => 40,
        'started_at' => now(),
    ]);

    $this->withToken($this->token)->getJson("/api/operations/{$deployment->operation_id}")
        ->assertOk()
        ->assertJson(['data' => [
            'id' => $deployment->operation_id,
            'type' => 'deploiement',
            'statut' => 'en_cours',
            'etape' => 'Construction de l\'image',
            'progression' => 40,
            'erreur' => null,
            'projet_id' => $this->project->id,
            'deploiement_id' => $deployment->id,
            'termine_le' => null,
        ]]);
});

test('une opération échouée donne le code et le message de l\'erreur', function () {
    $operation = Operation::factory()->for($this->project)->failed()->create();

    $this->withToken($this->token)->getJson("/api/operations/{$operation->id}")
        ->assertOk()
        ->assertJsonPath('data.statut', 'echouee')
        ->assertJsonPath('data.erreur.code', 'build_echoue')
        ->assertJsonPath('data.erreur.message', 'La construction de l\'image a échoué.')
        // Opération sans déploiement associé.
        ->assertJsonPath('data.deploiement_id', null);
});

test('l\'opération d\'un autre compte est introuvable', function () {
    $operation = Operation::factory()->create();

    $this->withToken($this->token)->getJson("/api/operations/{$operation->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'introuvable');
});

test('un identifiant inconnu ou mal formé donne 404', function (string $id) {
    $this->withToken($this->token)->getJson("/api/operations/{$id}")->assertStatus(404);
})->with([
    'ULID inexistant' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
    'pas un ULID' => ['123'],
]);

test('un jeton IA suit les opérations de son compte, et un jeton est obligatoire', function () {
    $operation = Operation::factory()->for($this->project)->create();

    $this->getJson("/api/operations/{$operation->id}")->assertStatus(401);

    $this->withToken(agentTokenFor($this->user))->getJson("/api/operations/{$operation->id}")->assertOk();
});
