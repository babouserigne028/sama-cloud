<?php

declare(strict_types=1);

use App\Application\Showcase\Contracts\RepositorySource;
use App\Application\Showcase\Jobs\ImportRepository;
use App\Domain\Showcase\Enums\ImportStatus;
use App\Models\Showcase;
use App\Models\ShowcaseFile;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\FakeRepositorySource;

/*
 * Vitrine de projets : présenter un projet à partir d'un dépôt GitHub, lire son code, le télécharger.
 * GitHub est remplacé par une fausse source : aucun appel réseau.
 */

beforeEach(function () {
    Technology::factory()->create(['slug' => 'laravel', 'name' => 'Laravel']);
    Technology::factory()->create(['slug' => 'wave', 'name' => 'Wave']);

    $this->source = new FakeRepositorySource;
    $this->app->instance(RepositorySource::class, $this->source);

    $this->awa = User::factory()->create(['name' => 'Awa Diop', 'country_code' => 'SN']);
    $this->moussa = User::factory()->create(['name' => 'Moussa Koné']);
    $this->awaToken = sessionTokenFor($this->awa);
    $this->moussaToken = sessionTokenFor($this->moussa);
});

function validShowcase(array $overrides = []): array
{
    return [
        'titre' => 'Paiement Wave pour Laravel',
        'description' => 'Intégration du paiement **Wave** dans une application Laravel.',
        'depot' => 'https://github.com/awa-diop/laravel-wave',
        'technologies' => ['laravel', 'wave'],
        ...$overrides,
    ];
}

function useToken(string $token): TestCase
{
    forgetAuthenticatedUser();

    return test()->withToken($token);
}

// --- Présenter un projet ---

test('présenter un projet répond tout de suite et met l\'import en file d\'attente', function () {
    Queue::fake();

    $response = $this->withToken($this->awaToken)->postJson('/api/vitrine', validShowcase([
        'branche' => 'main',
        'demo_url' => 'https://wave-demo.samacloud.piitech.dev',
    ]));

    $showcase = Showcase::query()->sole();

    $response->assertStatus(202)->assertJson(['data' => [
        'id' => $showcase->id,
        'titre' => 'Paiement Wave pour Laravel',
        'auteur' => ['pseudo' => 'awa-diop', 'nom' => 'Awa Diop', 'pays' => 'SN'],
        'depot' => 'https://github.com/awa-diop/laravel-wave',
        'branche' => 'main',
        'demo_url' => 'https://wave-demo.samacloud.piitech.dev',
        'import' => ['statut' => 'en_attente', 'erreur' => null, 'termine_le' => null],
        'nb_fichiers' => 0,
        'incomplet' => false,
    ]]);

    expect($showcase->user_id)->toBe($this->awa->id);

    Queue::assertPushed(ImportRepository::class, fn (ImportRepository $job) => $job->showcaseId === $showcase->id);
    Queue::assertPushed(ImportRepository::class, 1);
});

test('une fois l\'import terminé, le code du dépôt est disponible', function () {
    $this->source->files = ['README.md' => "# Laravel Wave\n", 'src/Wave.php' => "<?php\n\nfinal class Wave {}\n"];

    // La file d'attente des tests exécute la tâche aussitôt.
    $id = $this->withToken($this->awaToken)->postJson('/api/vitrine', validShowcase(['depot' => 'https://github.com/awa-diop/laravel-wave.git', 'branche' => 'develop']))
        ->assertStatus(202)
        ->assertJsonPath('data.import.statut', 'termine')
        ->assertJsonPath('data.nb_fichiers', 2)
        ->json('data.id');

    expect($this->source->requests)->toBe([['depot' => 'https://github.com/awa-diop/laravel-wave', 'branche' => 'develop']]);

    $this->getJson("/api/vitrine/{$id}/fichiers")->assertOk()->assertExactJson(['data' => [
        ['chemin' => 'README.md', 'taille_octets' => 15],
        ['chemin' => 'src/Wave.php', 'taille_octets' => 27],
    ]]);

    $this->getJson("/api/vitrine/{$id}")
        ->assertOk()
        ->assertJsonPath('data.taille_octets', 42)
        ->assertJsonPath('data.import.erreur', null);
});

test('un import qui échoue est signalé sur le projet, sans fichier', function (string $reason) {
    $this->source->failure = $reason;

    $this->withToken($this->awaToken)->postJson('/api/vitrine', validShowcase())
        ->assertStatus(202)
        ->assertJsonPath('data.import.statut', 'echec')
        ->assertJsonPath('data.import.erreur', $reason)
        ->assertJsonPath('data.nb_fichiers', 0);

    expect(ShowcaseFile::query()->count())->toBe(0);
})->with(['depot_introuvable', 'depot_trop_volumineux', 'archive_invalide', 'depot_sans_code', 'github_injoignable']);

test('un dépôt qui dépasse les limites est marqué incomplet', function () {
    $this->source->truncated = true;

    $this->withToken($this->awaToken)->postJson('/api/vitrine', validShowcase())
        ->assertStatus(202)
        ->assertJsonPath('data.import.statut', 'termine')
        ->assertJsonPath('data.incomplet', true);
});

test('une présentation invalide est refusée et rien n\'est importé', function (array $overrides, string $invalidField) {
    $response = $this->withToken($this->awaToken)->postJson('/api/vitrine', validShowcase($overrides));

    $response->assertStatus(422);

    expect(collect($response->json('details'))->pluck('champ')->all())->toContain($invalidField)
        ->and(Showcase::query()->count())->toBe(0)
        ->and($this->source->requests)->toBe([]);
})->with([
    'titre trop court' => [['titre' => 'ab'], 'titre'],
    'description trop courte' => [['description' => 'Trop court'], 'description'],
    'dépôt absent' => [['depot' => ''], 'depot'],
    'dépôt hors GitHub' => [['depot' => 'https://gitlab.com/awa/projet'], 'depot'],
    'faux GitHub' => [['depot' => 'https://github.com.pirate.io/awa/projet'], 'depot'],
    'adresse interne' => [['depot' => 'http://127.0.0.1/awa/projet'], 'depot'],
    'branche dangereuse' => [['branche' => '--upload-pack=x'], 'branche'],
    'démo non chiffrée' => [['demo_url' => 'http://demo.exemple.sn'], 'demo_url'],
    'démo javascript' => [['demo_url' => 'javascript:alert(1)'], 'demo_url'],
    'sans technologie' => [['technologies' => []], 'technologies'],
    'technologie inconnue' => [['technologies' => ['cobol-1959']], 'technologies.0'],
]);

// --- Parcourir la vitrine ---

test('la vitrine publique ne liste que les projets dont le code est disponible', function () {
    $visible = Showcase::factory()->for($this->awa, 'owner')->create(['title' => 'Projet visible', 'description' => str_repeat('Description longue. ', 30)]);
    Showcase::factory()->for($this->awa, 'owner')->pending()->create();
    Showcase::factory()->for($this->awa, 'owner')->failed()->create();
    Showcase::factory()->for($this->awa, 'owner')->create()->delete();

    $response = $this->getJson('/api/vitrine');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.auteur.pseudo', 'awa-diop')
        ->assertJsonPath('meta.total', 1);

    expect(mb_strlen($response->json('data.0.extrait')))->toBeLessThanOrEqual(203)
        ->and($response->json('data.0'))->not->toHaveKey('description')
        ->and($response->getContent())->not->toContain($this->awa->email);
});

test('la vitrine se filtre par texte et par technologie', function () {
    $wave = Showcase::factory()->create(['title' => 'Paiement Wave', 'description' => 'Module de paiement mobile money pour Laravel.']);
    $wave->technologies()->sync(Technology::query()->whereIn('slug', ['laravel', 'wave'])->pluck('id'));
    $stock = Showcase::factory()->create(['title' => 'Gestion de stock', 'description' => 'Application de suivi des entrepôts et des livraisons.']);
    $stock->technologies()->sync(Technology::query()->where('slug', 'laravel')->pluck('id'));

    $this->getJson('/api/vitrine?q=WAVE')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wave->id);
    $this->getJson('/api/vitrine?q='.urlencode('entrepôts'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stock->id);
    $this->getJson('/api/vitrine?technologie=wave')->assertJsonCount(1, 'data');
    $this->getJson('/api/vitrine?technologie=laravel')->assertJsonCount(2, 'data');
    $this->getJson('/api/vitrine?q='.urlencode('%'))->assertJsonCount(0, 'data');
    $this->getJson('/api/vitrine?par_page=51')->assertStatus(422);
});

// --- Lire le code ---

test('un fichier se lit sans jeton, tel quel, sans jamais être exécuté', function () {
    $showcase = Showcase::factory()->create();
    $content = "<?php\n\n// <script>alert('xss')</script>\necho shell_exec('rm -rf /');\n";
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'src/danger.php', 'content' => $content, 'size' => strlen($content)]);

    $this->getJson("/api/vitrine/{$showcase->id}/fichiers/contenu?chemin=".urlencode('src/danger.php'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['data' => ['chemin' => 'src/danger.php', 'taille_octets' => strlen($content), 'contenu' => $content]]);
});

test('on ne lit pas un fichier absent, d\'un autre projet, ou par un chemin piégé', function (string $path) {
    $showcase = Showcase::factory()->create();
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'README.md']);
    ShowcaseFile::factory()->create(['path' => 'secret-autre-projet.md']);

    $this->getJson("/api/vitrine/{$showcase->id}/fichiers/contenu?chemin=".urlencode($path))->assertStatus(404);
})->with(['absent.md', 'secret-autre-projet.md', '../../.env', '/etc/passwd']);

test('le chemin du fichier est obligatoire, et un projet inconnu ou supprimé est introuvable', function () {
    $showcase = Showcase::factory()->create();
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'README.md']);

    $this->getJson("/api/vitrine/{$showcase->id}/fichiers/contenu")->assertStatus(422);

    $showcase->delete();

    $this->getJson("/api/vitrine/{$showcase->id}")->assertStatus(404);
    $this->getJson("/api/vitrine/{$showcase->id}/fichiers")->assertStatus(404);
    $this->getJson("/api/vitrine/{$showcase->id}/fichiers/contenu?chemin=README.md")->assertStatus(404);
    $this->get("/api/vitrine/{$showcase->id}/archive")->assertStatus(404);
    $this->getJson('/api/vitrine/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertStatus(404);
});

test('le projet se télécharge en .zip, avec exactement ses fichiers', function () {
    $showcase = Showcase::factory()->create(['repository_name' => 'laravel-wave', 'files_count' => 2]);
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'README.md', 'content' => "# Laravel Wave\n"]);
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'src/Wave.php', 'content' => "<?php\n\nfinal class Wave {}\n"]);

    $response = $this->get("/api/vitrine/{$showcase->id}/archive");

    $response->assertOk()->assertDownload('laravel-wave.zip');

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
    }
    $zip->close();

    expect($entries)->toBe([
        'laravel-wave/README.md' => "# Laravel Wave\n",
        'laravel-wave/src/Wave.php' => "<?php\n\nfinal class Wave {}\n",
    ]);
});

test('un projet sans fichier importé n\'a pas d\'archive', function () {
    $showcase = Showcase::factory()->pending()->create();

    $this->get("/api/vitrine/{$showcase->id}/archive")->assertStatus(404);
});

// --- Modifier, relancer, retirer ---

test('le propriétaire modifie la présentation de son projet, pas son dépôt', function () {
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create();

    $this->withToken($this->awaToken)->patchJson("/api/vitrine/{$showcase->id}", [
        'titre' => 'Nouveau titre du projet',
        'demo_url' => 'https://demo.samacloud.piitech.dev',
        'technologies' => ['wave'],
        'depot' => 'https://github.com/pirate/autre-depot',
    ])
        ->assertOk()
        ->assertJsonPath('data.titre', 'Nouveau titre du projet')
        ->assertJsonPath('data.demo_url', 'https://demo.samacloud.piitech.dev')
        ->assertJsonPath('data.depot', 'https://github.com/awa-diop/laravel-wave')
        ->assertJsonPath('data.description', 'Intégration du paiement **Wave** dans une application Laravel.')
        ->assertJsonCount(1, 'data.technologies');
});

test('relancer l\'import remplace les fichiers par ceux du dépôt', function () {
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create(['files_count' => 1]);
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'ancien-fichier.md']);
    $this->source->files = ['nouveau.md' => "# Nouveau\n"];

    $this->withToken($this->awaToken)->postJson("/api/vitrine/{$showcase->id}/import")
        ->assertStatus(202)
        ->assertJsonPath('data.import.statut', 'termine')
        ->assertJsonPath('data.nb_fichiers', 1);

    expect($showcase->files()->pluck('path')->all())->toBe(['nouveau.md']);
});

test('un import en échec garde les anciens fichiers, et peut être relancé', function () {
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create(['files_count' => 1]);
    ShowcaseFile::factory()->for($showcase)->create(['path' => 'README.md']);
    $this->source->failure = 'github_injoignable';

    $this->withToken($this->awaToken)->postJson("/api/vitrine/{$showcase->id}/import")
        ->assertStatus(202)
        ->assertJsonPath('data.import.statut', 'echec');

    expect($showcase->files()->pluck('path')->all())->toBe(['README.md']);

    $this->source->failure = null;

    $this->withToken($this->awaToken)->postJson("/api/vitrine/{$showcase->id}/import")
        ->assertStatus(202)
        ->assertJsonPath('data.import.statut', 'termine')
        ->assertJsonPath('data.import.erreur', null);
});

test('on ne relance pas un import déjà en cours', function (ImportStatus $status) {
    Queue::fake();
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create(['import_status' => $status]);

    $this->withToken($this->awaToken)->postJson("/api/vitrine/{$showcase->id}/import")
        ->assertStatus(409)
        ->assertJsonPath('code', 'import_en_cours');

    Queue::assertNothingPushed();
})->with([ImportStatus::Pending, ImportStatus::Running]);

test('seul le propriétaire modifie, relance ou retire son projet', function () {
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create(['title' => 'Titre d\'origine']);

    $this->withToken($this->moussaToken)->patchJson("/api/vitrine/{$showcase->id}", ['titre' => 'Titre volé'])->assertStatus(403);
    $this->withToken($this->moussaToken)->postJson("/api/vitrine/{$showcase->id}/import")->assertStatus(403);
    $this->withToken($this->moussaToken)->deleteJson("/api/vitrine/{$showcase->id}")->assertStatus(403);

    expect($showcase->fresh()?->title)->toBe('Titre d\'origine')
        ->and($this->source->requests)->toBe([]);

    useToken($this->awaToken)->deleteJson("/api/vitrine/{$showcase->id}")->assertNoContent();

    $this->getJson("/api/vitrine/{$showcase->id}")->assertStatus(404);
});

test('un administrateur peut retirer un projet, mais pas le modifier', function () {
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create();
    $adminToken = sessionTokenFor(User::factory()->admin()->create());

    $this->withToken($adminToken)->patchJson("/api/vitrine/{$showcase->id}", ['titre' => 'Titre réécrit'])->assertStatus(403);
    $this->withToken($adminToken)->deleteJson("/api/vitrine/{$showcase->id}")->assertNoContent();
});

test('publier dans la vitrine exige un jeton de session', function (string $method, string $uri) {
    $showcase = Showcase::factory()->for($this->awa, 'owner')->create();
    $uri = str_replace('{id}', $showcase->id, $uri);

    $this->json($method, $uri, validShowcase())->assertStatus(401);

    useToken(agentTokenFor($this->awa))->json($method, $uri, validShowcase())
        ->assertStatus(403)
        ->assertJsonPath('code', 'capacite_manquante');

    expect(Showcase::query()->count())->toBe(1)->and($this->source->requests)->toBe([]);
})->with([
    'présenter' => ['POST', '/api/vitrine'],
    'modifier' => ['PATCH', '/api/vitrine/{id}'],
    'relancer l\'import' => ['POST', '/api/vitrine/{id}/import'],
    'retirer' => ['DELETE', '/api/vitrine/{id}'],
]);

test('la tâche d\'import ignore un projet supprimé entre-temps', function () {
    $showcase = Showcase::factory()->pending()->create();
    $showcase->delete();

    (new ImportRepository($showcase->id))->handle($this->source);

    expect($this->source->requests)->toBe([]);
});

test('une panne imprévue pendant l\'import ne laisse pas le projet bloqué « en cours »', function () {
    $showcase = Showcase::factory()->create(['import_status' => ImportStatus::Running]);

    (new ImportRepository($showcase->id))->failed(new RuntimeException('panne'));

    expect($showcase->fresh()?->import_status)->toBe(ImportStatus::Failed)
        ->and($showcase->fresh()?->import_error)->toBe('erreur_interne');
});
