<?php

declare(strict_types=1);

use App\Application\Community\ReputationLedger;
use App\Domain\Showcase\Enums\ImportStatus;
use App\Models\Showcase;
use App\Models\ShowcaseComment;
use App\Models\ShowcaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

/*
 * Vitrine, seconde moitié : modifier les fichiers de la copie SamaCloud, donner des étoiles, commenter.
 */

beforeEach(function () {
    $this->awa = User::factory()->create(['name' => 'Awa Diop']);
    $this->moussa = User::factory()->create(['name' => 'Moussa Koné']);
    $this->fatou = User::factory()->create(['name' => 'Fatou Traoré']);
    $this->awaToken = sessionTokenFor($this->awa);
    $this->moussaToken = sessionTokenFor($this->moussa);
    $this->fatouToken = sessionTokenFor($this->fatou);

    // Projet d'Awa, déjà importé, avec un fichier de 15 octets.
    $this->showcase = Showcase::factory()->for($this->awa, 'owner')->create(['files_count' => 1, 'total_bytes' => 15]);
    ShowcaseFile::factory()->for($this->showcase)->create(['path' => 'README.md', 'content' => "# Laravel Wave\n", 'size' => 15]);

    $this->fileUrl = "/api/vitrine/{$this->showcase->id}/fichiers/contenu";
});

function asUser(string $token): TestCase
{
    forgetAuthenticatedUser();

    return test()->withToken($token);
}

function showcasePoints(User $user): int
{
    return app(ReputationLedger::class)->pointsOf($user->id);
}

// --- Modifier les fichiers ---

test('le propriétaire modifie un fichier, au caractère près', function () {
    // Espaces en tête, tabulation et saut de ligne final : rien ne doit être rogné.
    $content = "  <?php\n\n\techo 'Bonjour';\n\n";

    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'README.md', 'contenu' => $content])
        ->assertOk()
        ->assertExactJson(['data' => ['chemin' => 'README.md', 'taille_octets' => strlen($content), 'contenu' => $content]]);

    $this->getJson($this->fileUrl.'?chemin=README.md')->assertJsonPath('data.contenu', $content);

    $this->getJson("/api/vitrine/{$this->showcase->id}")
        ->assertJsonPath('data.nb_fichiers', 1)
        ->assertJsonPath('data.taille_octets', strlen($content));
});

test('le propriétaire ajoute un fichier, y compris vide, et les compteurs suivent', function () {
    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'src/Wave.php', 'contenu' => "<?php\n"])->assertOk();
    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'src/.gitkeep', 'contenu' => ''])
        ->assertOk()
        ->assertJsonPath('data.taille_octets', 0)
        ->assertJsonPath('data.contenu', '');

    $this->getJson("/api/vitrine/{$this->showcase->id}/fichiers")->assertJsonCount(3, 'data');

    expect($this->showcase->fresh()?->files_count)->toBe(3)
        ->and($this->showcase->fresh()?->total_bytes)->toBe(15 + 6);
});

test('le propriétaire supprime un fichier', function () {
    $this->withToken($this->awaToken)->deleteJson($this->fileUrl.'?chemin=README.md')->assertNoContent();

    $this->getJson($this->fileUrl.'?chemin=README.md')->assertStatus(404);

    expect($this->showcase->fresh()?->files_count)->toBe(0)
        ->and($this->showcase->fresh()?->total_bytes)->toBe(0);

    $this->withToken($this->awaToken)->deleteJson($this->fileUrl.'?chemin=README.md')->assertStatus(404);
});

test('on ne peut pas enregistrer un secret, un binaire, un chemin piégé ou un fichier trop gros', function (array $payload, string $invalidField) {
    config()->set('samacloud.showcase.max_file_bytes', 100);

    $response = $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'src/ok.php', 'contenu' => 'ok', ...$payload]);

    $response->assertStatus(422)->assertJsonPath('details.0.champ', $invalidField);

    expect($this->showcase->files()->count())->toBe(1);
})->with([
    'fichier .env' => [['chemin' => '.env'], 'chemin'],
    'fichier .env de production' => [['chemin' => 'config/.env.production'], 'chemin'],
    'dossier de dépendances' => [['chemin' => 'node_modules/pirate/index.js'], 'chemin'],
    'image' => [['chemin' => 'logo.png'], 'chemin'],
    'remontée de dossier' => [['chemin' => '../autre-projet/secret.php'], 'chemin'],
    'chemin absolu' => [['chemin' => '/etc/passwd'], 'chemin'],
    'chemin absent' => [['chemin' => ''], 'chemin'],
    'contenu binaire' => [['contenu' => "PK\x03\x04\x00binaire"], 'contenu'],
    'contenu trop gros' => [['contenu' => str_repeat('x', 101)], 'contenu'],
    'contenu absent' => [['contenu' => ['pas', 'du', 'texte']], 'contenu'],
]);

test('les limites du projet sont respectées quand on ajoute des fichiers', function () {
    config()->set('samacloud.showcase.max_files', 2);
    config()->set('samacloud.showcase.max_total_bytes', 40);

    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'a.txt', 'contenu' => str_repeat('a', 10)])->assertOk();

    // Troisième fichier : le nombre maximum est atteint.
    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'b.txt', 'contenu' => 'b'])
        ->assertStatus(409)
        ->assertExactJson([
            'code' => 'limite_vitrine_atteinte',
            'message' => "Ce projet a atteint la limite de la vitrine. Supprimez des fichiers avant d'en ajouter.",
            'details' => ['max_fichiers' => 2, 'max_taille_octets' => 40],
        ]);

    // Agrandir un fichier existant au-delà de la taille totale est refusé aussi.
    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'a.txt', 'contenu' => str_repeat('a', 26)])->assertStatus(409);

    // Mais on peut toujours modifier un fichier existant dans les limites (15 + 25 = 40).
    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'a.txt', 'contenu' => str_repeat('a', 25)])->assertOk();

    expect($this->showcase->fresh()?->total_bytes)->toBe(40)->and($this->showcase->files()->count())->toBe(2);
});

test('on ne modifie pas les fichiers pendant un import', function (ImportStatus $status) {
    $this->showcase->update(['import_status' => $status]);

    $this->withToken($this->awaToken)->putJson($this->fileUrl, ['chemin' => 'README.md', 'contenu' => 'modifié'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'import_en_cours');

    $this->withToken($this->awaToken)->deleteJson($this->fileUrl.'?chemin=README.md')->assertStatus(409);

    expect($this->showcase->files()->sole()->content)->toBe("# Laravel Wave\n");
})->with([ImportStatus::Pending, ImportStatus::Running]);

test('seul le propriétaire modifie les fichiers de son projet', function () {
    $this->withToken($this->moussaToken)->putJson($this->fileUrl, ['chemin' => 'README.md', 'contenu' => 'piraté'])->assertStatus(403);
    $this->withToken($this->moussaToken)->deleteJson($this->fileUrl.'?chemin=README.md')->assertStatus(403);

    // Un administrateur modère (il peut retirer le projet), mais ne réécrit pas le code des autres.
    asUser(sessionTokenFor(User::factory()->admin()->create()))->putJson($this->fileUrl, ['chemin' => 'README.md', 'contenu' => 'réécrit'])->assertStatus(403);

    asUser(agentTokenFor($this->awa))->putJson($this->fileUrl, ['chemin' => 'README.md', 'contenu' => 'écrit par une IA'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'capacite_manquante');

    forgetAuthenticatedUser();
    $this->flushHeaders()->putJson($this->fileUrl, ['chemin' => 'README.md', 'contenu' => 'anonyme'])->assertStatus(401);

    expect($this->showcase->files()->sole()->content)->toBe("# Laravel Wave\n");
});

// --- Étoiles ---

test('une étoile compte une seule fois par compte, rapporte 5 points et peut être retirée', function () {
    $url = "/api/vitrine/{$this->showcase->id}/etoile";

    $this->withToken($this->moussaToken)->putJson($url)
        ->assertOk()
        ->assertExactJson(['data' => ['projet_id' => $this->showcase->id, 'nb_etoiles' => 1, 'etoile_par_moi' => true]]);

    $this->withToken($this->moussaToken)->putJson($url)->assertOk()->assertJsonPath('data.nb_etoiles', 1);

    asUser($this->fatouToken)->putJson($url)->assertOk()->assertJsonPath('data.nb_etoiles', 2);

    expect(showcasePoints($this->awa))->toBe(10)
        ->and(DB::table('showcase_stars')->count())->toBe(2);

    $this->getJson('/api/profils/awa-diop')->assertJsonPath('data.points', 10);

    // Retirer son étoile ne reprend que ses propres points.
    asUser($this->moussaToken)->deleteJson($url)
        ->assertOk()
        ->assertExactJson(['data' => ['projet_id' => $this->showcase->id, 'nb_etoiles' => 1, 'etoile_par_moi' => false]]);

    $this->withToken($this->moussaToken)->deleteJson($url)->assertOk();

    expect(showcasePoints($this->awa))->toBe(5);
});

test('le projet indique à chacun s\'il a donné une étoile', function () {
    $this->showcase->stargazers()->attach($this->moussa->id, ['created_at' => now()]);
    $url = "/api/vitrine/{$this->showcase->id}";

    $this->getJson($url)->assertJsonPath('data.nb_etoiles', 1)->assertJsonPath('data.etoile_par_moi', false);
    asUser($this->moussaToken)->getJson($url)->assertJsonPath('data.etoile_par_moi', true);
    asUser($this->fatouToken)->getJson($url)->assertJsonPath('data.etoile_par_moi', false);
});

test('on ne donne pas d\'étoile à son propre projet, ni avec un jeton IA, ni sans jeton', function () {
    $url = "/api/vitrine/{$this->showcase->id}/etoile";

    $this->withToken($this->awaToken)->putJson($url)->assertStatus(403)->assertJsonPath('code', 'etoile_sur_son_projet');
    asUser(agentTokenFor($this->moussa))->putJson($url)->assertStatus(403)->assertJsonPath('code', 'capacite_manquante');

    forgetAuthenticatedUser();
    $this->flushHeaders()->putJson($url)->assertStatus(401);

    expect(DB::table('showcase_stars')->count())->toBe(0)->and(showcasePoints($this->awa))->toBe(0);
});

test('l\'étoile d\'un compte tout juste créé est comptée mais ne rapporte pas de points', function () {
    config()->set('samacloud.community.min_account_age_hours_for_points', 24);

    $this->withToken($this->moussaToken)->putJson("/api/vitrine/{$this->showcase->id}/etoile")
        ->assertOk()
        ->assertJsonPath('data.nb_etoiles', 1);

    expect(showcasePoints($this->awa))->toBe(0);
});

test('la vitrine peut être triée par nombre d\'étoiles', function () {
    $popular = Showcase::factory()->create(['title' => 'Projet populaire', 'created_at' => now()->subDays(3)]);
    $popular->stargazers()->attach([$this->moussa->id => ['created_at' => now()], $this->fatou->id => ['created_at' => now()]]);
    ShowcaseComment::factory()->for($popular)->count(3)->create();

    $this->getJson('/api/vitrine')->assertJsonPath('data.0.id', $this->showcase->id);

    $this->getJson('/api/vitrine?tri=etoiles')
        ->assertOk()
        ->assertJsonPath('data.0.id', $popular->id)
        ->assertJsonPath('data.0.nb_etoiles', 2)
        ->assertJsonPath('data.0.nb_commentaires', 3)
        ->assertJsonPath('data.1.nb_etoiles', 0);

    $this->getJson('/api/vitrine?tri=hasard')->assertStatus(422);
});

test('retirer un projet reprend les points de ses étoiles', function () {
    $this->withToken($this->moussaToken)->putJson("/api/vitrine/{$this->showcase->id}/etoile")->assertOk();

    expect(showcasePoints($this->awa))->toBe(5);

    asUser($this->awaToken)->deleteJson("/api/vitrine/{$this->showcase->id}")->assertNoContent();

    expect(showcasePoints($this->awa))->toBe(0);

    asUser($this->moussaToken)->putJson("/api/vitrine/{$this->showcase->id}/etoile")->assertStatus(404);
});

// --- Commentaires ---

test('un développeur commente un projet, et le propriétaire est prévenu', function () {
    $response = $this->withToken($this->moussaToken)->postJson("/api/vitrine/{$this->showcase->id}/commentaires", [
        'contenu' => 'Très utile, merci ! <script>alert(1)</script>',
    ]);

    $response->assertCreated()->assertJson(['data' => [
        // Le texte est rendu tel quel : c'est à l'affichage de ne jamais l'insérer comme du HTML.
        'contenu' => 'Très utile, merci ! <script>alert(1)</script>',
        'auteur' => ['pseudo' => 'moussa-kone', 'nom' => 'Moussa Koné'],
    ]]);

    $this->getJson("/api/vitrine/{$this->showcase->id}")->assertJsonPath('data.nb_commentaires', 1);

    asUser($this->awaToken)->getJson('/api/notifications')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'commentaire_recu')
        ->assertJsonPath('data.0.donnees.projet_id', $this->showcase->id)
        ->assertJsonPath('data.0.donnees.commentaire_id', $response->json('data.id'))
        ->assertJsonPath('data.0.donnees.auteur.pseudo', 'moussa-kone');
});

test('commenter son propre projet ne déclenche aucune notification', function () {
    $this->withToken($this->awaToken)->postJson("/api/vitrine/{$this->showcase->id}/commentaires", ['contenu' => 'Merci à tous pour vos retours.'])
        ->assertCreated();

    expect($this->awa->notifications()->count())->toBe(0);
});

test('les commentaires se lisent sans jeton, du plus récent au plus ancien, par pages', function () {
    $old = ShowcaseComment::factory()->for($this->showcase)->create(['created_at' => now()->subDay()]);
    $recent = ShowcaseComment::factory()->for($this->showcase)->create();
    ShowcaseComment::factory()->create();
    ShowcaseComment::factory()->for($this->showcase)->create()->delete();

    $this->getJson("/api/vitrine/{$this->showcase->id}/commentaires")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $recent->id)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('meta.total', 2);

    ShowcaseComment::factory()->for($this->showcase)->count(20)->create();

    $this->getJson("/api/vitrine/{$this->showcase->id}/commentaires?page=2")->assertJsonCount(2, 'data');
});

test('un commentaire vide, trop long, ou sur un projet introuvable est refusé', function () {
    $url = "/api/vitrine/{$this->showcase->id}/commentaires";

    $this->withToken($this->moussaToken)->postJson($url, ['contenu' => ''])->assertStatus(422);
    $this->withToken($this->moussaToken)->postJson($url, ['contenu' => str_repeat('a', 2001)])->assertStatus(422);
    $this->withToken($this->moussaToken)->postJson('/api/vitrine/01ARZ3NDEKTSV4RRFFQ69G5FAV/commentaires', ['contenu' => 'Bonjour'])->assertStatus(404);

    expect(ShowcaseComment::query()->count())->toBe(0);
});

test('un commentaire est supprimé par son auteur, par le propriétaire du projet ou par un administrateur', function (string $who) {
    $comment = ShowcaseComment::factory()->for($this->showcase)->for($this->moussa, 'author')->create();

    $token = match ($who) {
        'auteur' => $this->moussaToken,
        'propriétaire du projet' => $this->awaToken,
        'administrateur' => sessionTokenFor(User::factory()->admin()->create()),
    };

    $this->withToken($token)->deleteJson("/api/commentaires/{$comment->id}")->assertNoContent();

    $this->getJson("/api/vitrine/{$this->showcase->id}/commentaires")->assertJsonCount(0, 'data');
})->with(['auteur', 'propriétaire du projet', 'administrateur']);

test('un tiers ne supprime pas le commentaire d\'un autre', function () {
    $comment = ShowcaseComment::factory()->for($this->showcase)->for($this->moussa, 'author')->create();

    $this->withToken($this->fatouToken)->deleteJson("/api/commentaires/{$comment->id}")->assertStatus(403);
    asUser(agentTokenFor($this->moussa))->deleteJson("/api/commentaires/{$comment->id}")->assertStatus(403);

    forgetAuthenticatedUser();
    $this->flushHeaders()->deleteJson("/api/commentaires/{$comment->id}")->assertStatus(401);

    expect(ShowcaseComment::query()->count())->toBe(1);
});

test('le commentaire d\'un projet retiré est introuvable', function () {
    $comment = ShowcaseComment::factory()->for($this->showcase)->for($this->moussa, 'author')->create();
    $this->showcase->delete();

    $this->withToken($this->moussaToken)->deleteJson("/api/commentaires/{$comment->id}")->assertStatus(404);
    $this->getJson("/api/vitrine/{$this->showcase->id}/commentaires")->assertStatus(404);
});
