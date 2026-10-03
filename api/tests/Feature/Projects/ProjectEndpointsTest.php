<?php

declare(strict_types=1);

use App\Domain\Project\Enums\ResourceSize;
use App\Models\Project;
use App\Models\ProjectDatabase;
use App\Models\ProjectService;
use App\Models\User;

/*
 * GET /api/projets et GET /api/projets/{projet} (outils MCP lister_projets et etat_projet).
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = sessionTokenFor($this->user);
});

test('la liste renvoie les projets du compte, du plus récent au plus ancien', function () {
    Project::factory()->for($this->user)->create(['name' => 'ancien-projet', 'slug' => 'ancien-projet', 'created_at' => now()->subDay()]);
    Project::factory()->for($this->user)->create(['name' => 'nouveau-projet', 'slug' => 'nouveau-projet']);

    $response = $this->withToken($this->token)->getJson('/api/projets');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nom', 'nouveau-projet')
        ->assertJsonPath('data.1.nom', 'ancien-projet');
});

test('la liste ne montre ni les projets des autres comptes ni les projets supprimés', function () {
    Project::factory()->for($this->user)->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);
    Project::factory()->for($this->user)->create(['name' => 'efface', 'slug' => 'efface'])->delete();
    Project::factory()->create(['name' => 'projet-voisin', 'slug' => 'projet-voisin']);

    $response = $this->withToken($this->token)->getJson('/api/projets');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nom', 'mon-blog');
});

test('un compte sans projet reçoit une liste vide', function () {
    $this->withToken($this->token)->getJson('/api/projets')->assertOk()->assertExactJson(['data' => []]);
});

test('le détail d\'un projet donne son statut, son adresse, ses services, ses bases et son échéance', function () {
    $this->freezeTime();

    $project = Project::factory()->for($this->user)->create([
        'name' => 'mon-blog',
        'slug' => 'mon-blog',
        'repository_url' => 'https://github.com/awa/mon-blog.git',
        'branch' => 'main',
        'paid_until' => now()->addDays(12),
    ]);
    ProjectService::factory()->for($project)->create(['name' => 'web', 'hostname' => 'mon-blog.samacloud.piitech.dev']);
    ProjectService::factory()->for($project)->worker()->create();
    ProjectDatabase::factory()->for($project)->create(['size' => ResourceSize::Medium, 'credentials' => ['mot_de_passe' => 'tres-secret']]);

    $response = $this->withToken($this->token)->getJson('/api/projets/mon-blog');

    $response->assertOk()->assertJson(['data' => [
        'id' => $project->id,
        'nom' => 'mon-blog',
        'statut' => 'actif',
        'url' => 'https://mon-blog.samacloud.piitech.dev',
        'depot' => 'https://github.com/awa/mon-blog.git',
        'branche' => 'main',
        'echeance' => now()->addDays(12)->toIso8601String(),
        'bases' => [
            ['nom' => 'principale', 'type' => 'postgresql', 'version' => '16', 'taille' => 'moyenne'],
        ],
    ]]);

    expect($response->json('data.services'))->toEqualCanonicalizing([
        ['nom' => 'web', 'type' => 'web', 'framework' => 'laravel', 'taille' => 'petite', 'url' => 'https://mon-blog.samacloud.piitech.dev'],
        ['nom' => 'file-attente', 'type' => 'worker', 'framework' => 'laravel', 'taille' => 'petite', 'url' => null],
    ]);

    // Aucun secret ne sort : ni identifiants de base, ni configuration interne.
    expect($response->getContent())->not->toContain('tres-secret')
        ->and($response->json('data'))->not->toHaveKeys(['config', 'slug', 'user_id']);
});

test('un projet pas encore en ligne n\'a pas d\'adresse', function () {
    $project = Project::factory()->for($this->user)->pendingPayment()->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);
    ProjectService::factory()->for($project)->create(['hostname' => null]);

    $this->withToken($this->token)->getJson('/api/projets/mon-blog')
        ->assertOk()
        ->assertJsonPath('data.statut', 'en_attente_paiement')
        ->assertJsonPath('data.url', null)
        ->assertJsonPath('data.echeance', null);
});

test('le projet d\'un autre compte est introuvable, même si son nom est connu', function () {
    Project::factory()->create(['name' => 'projet-voisin', 'slug' => 'projet-voisin']);

    $this->withToken($this->token)->getJson('/api/projets/projet-voisin')
        ->assertStatus(404)
        ->assertJsonPath('code', 'introuvable');
});

test('un nom de projet inexistant, mal formé ou supprimé donne 404', function (string $name) {
    Project::factory()->for($this->user)->create(['name' => 'efface', 'slug' => 'efface'])->delete();

    $this->withToken($this->token)->getJson("/api/projets/{$name}")->assertStatus(404);
})->with(['inexistant', 'Mon_Blog', 'efface', 'ab']);

test('un jeton IA peut lire les projets de son compte', function () {
    Project::factory()->for($this->user)->create(['name' => 'mon-blog', 'slug' => 'mon-blog']);

    $agent = agentTokenFor($this->user);

    $this->withToken($agent)->getJson('/api/projets')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($agent)->getJson('/api/projets/mon-blog')->assertOk();
});

test('les projets exigent un jeton et un compte non suspendu', function () {
    $this->getJson('/api/projets')->assertStatus(401);

    $this->user->forceFill(['suspended_at' => now()])->save();

    $this->withToken($this->token)->getJson('/api/projets')
        ->assertStatus(403)
        ->assertJsonPath('code', 'compte_suspendu');
});
