<?php

declare(strict_types=1);

use App\Models\Answer;
use App\Models\Question;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

/*
 * Questions-réponses : poser une question, répondre, voter « Utile », accepter une réponse.
 */

beforeEach(function () {
    Technology::factory()->create(['slug' => 'laravel', 'name' => 'Laravel']);
    Technology::factory()->create(['slug' => 'wave', 'name' => 'Wave']);
    Technology::factory()->create(['slug' => 'flutter', 'name' => 'Flutter']);

    $this->awa = User::factory()->create(['name' => 'Awa Diop', 'country_code' => 'SN']);
    $this->moussa = User::factory()->create(['name' => 'Moussa Koné', 'country_code' => 'CI']);
    $this->awaToken = sessionTokenFor($this->awa);
    $this->moussaToken = sessionTokenFor($this->moussa);
});

function validQuestion(array $overrides = []): array
{
    return [
        'titre' => 'Comment intégrer le paiement Wave dans Laravel ?',
        'contenu' => "Je reçois une erreur 401.\n\n```php\nHttp::post(\$url);\n```",
        'technologies' => ['laravel', 'wave'],
        ...$overrides,
    ];
}

/**
 * Change de compte au milieu d'un test (voir forgetAuthenticatedUser).
 */
function actingWithToken(string $token): TestCase
{
    forgetAuthenticatedUser();

    return test()->withToken($token);
}

// --- Poser une question ---

test('un développeur pose une question avec du code et des technologies', function () {
    $response = $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion());

    $response->assertCreated()->assertJson(['data' => [
        'titre' => 'Comment intégrer le paiement Wave dans Laravel ?',
        'contenu' => "Je reçois une erreur 401.\n\n```php\nHttp::post(\$url);\n```",
        'auteur' => ['pseudo' => 'awa-diop', 'nom' => 'Awa Diop', 'pays' => 'SN'],
        'nb_reponses' => 0,
        'resolue' => false,
        'reponse_acceptee_id' => null,
        'reponses' => [],
    ]]);

    expect(collect($response->json('data.technologies'))->pluck('slug')->all())->toEqualCanonicalizing(['laravel', 'wave'])
        ->and(Question::query()->sole()->user_id)->toBe($this->awa->id);
});

test('l\'auteur d\'une question vient du jeton, jamais des données envoyées', function () {
    $this->withToken($this->awaToken)
        ->postJson('/api/questions', validQuestion(['user_id' => $this->moussa->id, 'auteur' => 'moussa-kone']))
        ->assertCreated();

    expect(Question::query()->sole()->user_id)->toBe($this->awa->id);
});

test('une question invalide est refusée', function (array $overrides, string $invalidField) {
    $response = $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion($overrides));

    $response->assertStatus(422);

    expect(collect($response->json('details'))->pluck('champ')->all())->toContain($invalidField)
        ->and(Question::query()->count())->toBe(0);
})->with([
    'titre trop court' => [['titre' => 'Aide'], 'titre'],
    'titre trop long' => [['titre' => str_repeat('a', 151)], 'titre'],
    'contenu trop court' => [['contenu' => 'Ça marche pas'], 'contenu'],
    'sans technologie' => [['technologies' => []], 'technologies'],
    'technologie inconnue' => [['technologies' => ['cobol-1959']], 'technologies.0'],
    'trop de technologies' => [['technologies' => ['laravel', 'wave', 'flutter', 'a', 'b', 'c']], 'technologies'],
    'technologie en double' => [['technologies' => ['laravel', 'laravel']], 'technologies.0'],
]);

// --- Lire et chercher ---

test('la liste des questions est publique, de la plus récente à la plus ancienne, avec un extrait', function () {
    $old = Question::factory()->for($this->awa, 'author')->create(['title' => 'Ancienne question sur Laravel', 'created_at' => now()->subDay()]);
    $recent = Question::factory()->for($this->moussa, 'author')->create(['title' => 'Question récente sur Flutter', 'body' => str_repeat('Texte. ', 100)]);
    Answer::factory()->for($old)->count(2)->create();

    $response = $this->getJson('/api/questions');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $recent->id)
        ->assertJsonPath('data.0.auteur.pseudo', 'moussa-kone')
        ->assertJsonPath('data.0.nb_reponses', 0)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.1.nb_reponses', 2)
        ->assertJsonPath('data.1.resolue', false)
        ->assertJsonPath('meta.total', 2);

    // L'extrait est limité : le contenu complet n'est pas dans la liste.
    expect(mb_strlen($response->json('data.0.extrait')))->toBeLessThanOrEqual(203)
        ->and($response->json('data.0'))->not->toHaveKey('contenu');
});

test('la recherche filtre par texte, par technologie et par statut', function () {
    $wave = Question::factory()->create(['title' => 'Erreur 401 avec Wave', 'body' => 'Le paiement échoue en production.']);
    $wave->technologies()->sync(Technology::query()->whereIn('slug', ['wave', 'laravel'])->pluck('id'));

    $flutter = Question::factory()->create(['title' => 'Navigation Flutter', 'body' => 'Comment revenir en arrière proprement ?']);
    $flutter->technologies()->sync(Technology::query()->where('slug', 'flutter')->pluck('id'));
    $flutter->update(['accepted_answer_id' => Answer::factory()->for($flutter)->create()->id]);

    $this->getJson('/api/questions?q=WAVE')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wave->id);
    $this->getJson('/api/questions?q='.urlencode('revenir en arrière'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $flutter->id);
    $this->getJson('/api/questions?technologie=laravel')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wave->id);
    $this->getJson('/api/questions?statut=resolue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $flutter->id);
    $this->getJson('/api/questions?statut=non_resolue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wave->id);
    $this->getJson('/api/questions?technologie=flutter&statut=non_resolue')->assertJsonCount(0, 'data');
    $this->getJson('/api/questions?q='.urlencode('%'))->assertJsonCount(0, 'data');
    $this->getJson('/api/questions?statut=peut-etre')->assertStatus(422);
});

test('une question se lit sans jeton, avec la réponse acceptée d\'abord puis les plus utiles', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $oldest = Answer::factory()->for($question)->create(['created_at' => now()->subHours(3)]);
    $mostUseful = Answer::factory()->for($question)->create(['created_at' => now()->subHours(2)]);
    $accepted = Answer::factory()->for($question)->create(['created_at' => now()->subHour()]);

    $mostUseful->voters()->attach(User::factory()->count(2)->create()->pluck('id'), ['created_at' => now()]);
    $question->update(['accepted_answer_id' => $accepted->id]);

    $response = $this->getJson("/api/questions/{$question->id}");

    $response->assertOk()
        ->assertJsonPath('data.resolue', true)
        ->assertJsonPath('data.reponse_acceptee_id', $accepted->id)
        ->assertJsonPath('data.nb_reponses', 3)
        ->assertJsonPath('data.reponses.0.id', $accepted->id)
        ->assertJsonPath('data.reponses.0.acceptee', true)
        ->assertJsonPath('data.reponses.1.id', $mostUseful->id)
        ->assertJsonPath('data.reponses.1.nb_utile', 2)
        ->assertJsonPath('data.reponses.1.acceptee', false)
        ->assertJsonPath('data.reponses.2.id', $oldest->id)
        // Sans jeton, personne n'a « voté ».
        ->assertJsonPath('data.reponses.1.vote_par_moi', false);

    expect($response->getContent())->not->toContain($this->awa->email);
});

test('une question inconnue, mal identifiée ou supprimée est introuvable', function () {
    $deleted = Question::factory()->create();
    $deleted->delete();

    $this->getJson('/api/questions/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertStatus(404);
    $this->getJson('/api/questions/123')->assertStatus(404);
    $this->getJson("/api/questions/{$deleted->id}")->assertStatus(404);
    $this->getJson('/api/questions')->assertJsonCount(0, 'data');
});

// --- Modifier et supprimer une question ---

test('l\'auteur modifie sa question, champ par champ', function () {
    $id = $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion())->json('data.id');

    $this->withToken($this->awaToken)->patchJson("/api/questions/{$id}", ['titre' => 'Erreur 401 avec le paiement Wave', 'technologies' => ['wave']])
        ->assertOk()
        ->assertJsonPath('data.titre', 'Erreur 401 avec le paiement Wave')
        ->assertJsonPath('data.contenu', "Je reçois une erreur 401.\n\n```php\nHttp::post(\$url);\n```")
        ->assertJsonCount(1, 'data.technologies');
});

test('on ne peut ni modifier ni supprimer la question d\'un autre', function () {
    $question = Question::factory()->for($this->awa, 'author')->create(['title' => 'Titre d\'origine de la question']);

    $this->withToken($this->moussaToken)->patchJson("/api/questions/{$question->id}", ['titre' => 'Titre changé par un autre'])
        ->assertStatus(403)->assertJsonPath('code', 'acces_refuse');
    $this->withToken($this->moussaToken)->deleteJson("/api/questions/{$question->id}")->assertStatus(403);

    expect($question->fresh()?->title)->toBe('Titre d\'origine de la question');
});

test('l\'auteur supprime sa question, qui disparaît avec ses réponses', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $answer = Answer::factory()->for($question)->for($this->moussa, 'author')->create();

    $this->withToken($this->awaToken)->deleteJson("/api/questions/{$question->id}")->assertNoContent();

    $this->getJson("/api/questions/{$question->id}")->assertStatus(404);

    // On ne peut plus agir sur une réponse dont la question a disparu.
    actingWithToken($this->moussaToken)->patchJson("/api/reponses/{$answer->id}", ['contenu' => 'Nouvelle version de ma réponse.'])->assertStatus(404);
    actingWithToken($this->awaToken)->putJson("/api/reponses/{$answer->id}/vote-utile")->assertStatus(404);
    actingWithToken($this->awaToken)->postJson("/api/questions/{$question->id}/reponses", ['contenu' => 'Réponse trop tardive.'])->assertStatus(404);
});

test('un administrateur peut retirer une question ou une réponse (modération)', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $answer = Answer::factory()->for($question)->for($this->moussa, 'author')->create();
    $adminToken = sessionTokenFor(User::factory()->admin()->create());

    $this->withToken($adminToken)->deleteJson("/api/reponses/{$answer->id}")->assertNoContent();
    $this->withToken($adminToken)->deleteJson("/api/questions/{$question->id}")->assertNoContent();

    // Mais un administrateur ne réécrit pas le contenu des autres.
    $other = Question::factory()->for($this->awa, 'author')->create();
    $this->withToken($adminToken)->patchJson("/api/questions/{$other->id}", ['titre' => 'Titre réécrit par un administrateur'])->assertStatus(403);
});

// --- Répondre ---

test('un développeur répond à une question', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();

    $response = $this->withToken($this->moussaToken)->postJson("/api/questions/{$question->id}/reponses", [
        'contenu' => "Ajoutez l'en-tête `Authorization`.",
    ]);

    $response->assertCreated()->assertJson(['data' => [
        'contenu' => "Ajoutez l'en-tête `Authorization`.",
        'auteur' => ['pseudo' => 'moussa-kone', 'nom' => 'Moussa Koné', 'pays' => 'CI'],
        'nb_utile' => 0,
        'acceptee' => false,
        'vote_par_moi' => false,
    ]]);

    $this->getJson("/api/questions/{$question->id}")->assertJsonPath('data.nb_reponses', 1);
});

test('une réponse trop courte est refusée, et on ne modifie ni ne supprime celle d\'un autre', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $answer = Answer::factory()->for($question)->for($this->moussa, 'author')->create(['body' => 'Réponse d\'origine de Moussa.']);

    $this->withToken($this->awaToken)->postJson("/api/questions/{$question->id}/reponses", ['contenu' => 'Oui'])->assertStatus(422);

    $this->withToken($this->awaToken)->patchJson("/api/reponses/{$answer->id}", ['contenu' => 'Réponse réécrite par un autre.'])->assertStatus(403);
    $this->withToken($this->awaToken)->deleteJson("/api/reponses/{$answer->id}")->assertStatus(403);

    expect($answer->fresh()?->body)->toBe('Réponse d\'origine de Moussa.');

    actingWithToken($this->moussaToken)->patchJson("/api/reponses/{$answer->id}", ['contenu' => 'Réponse corrigée par son auteur.'])
        ->assertOk()
        ->assertJsonPath('data.contenu', 'Réponse corrigée par son auteur.');
});

// --- Accepter une réponse ---

test('l\'auteur de la question accepte une réponse, puis en choisit une autre', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $first = Answer::factory()->for($question)->create();
    $second = Answer::factory()->for($question)->create();

    $this->withToken($this->awaToken)->putJson("/api/questions/{$question->id}/reponse-acceptee", ['reponse_id' => $first->id])
        ->assertOk()
        ->assertJsonPath('data.resolue', true)
        ->assertJsonPath('data.reponse_acceptee_id', $first->id);

    // Une seule réponse acceptée : la seconde remplace la première.
    $response = $this->withToken($this->awaToken)->putJson("/api/questions/{$question->id}/reponse-acceptee", ['reponse_id' => $second->id]);

    $accepted = collect($response->json('data.reponses'))->where('acceptee', true)->pluck('id')->all();

    expect($accepted)->toBe([$second->id]);
});

test('seul l\'auteur de la question peut accepter ou retirer une réponse', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $answer = Answer::factory()->for($question)->for($this->moussa, 'author')->create();

    // Même l'auteur de la réponse ne peut pas l'accepter lui-même.
    $this->withToken($this->moussaToken)->putJson("/api/questions/{$question->id}/reponse-acceptee", ['reponse_id' => $answer->id])
        ->assertStatus(403);

    expect($question->fresh()?->isResolved())->toBeFalse();

    $question->update(['accepted_answer_id' => $answer->id]);

    $this->withToken($this->moussaToken)->deleteJson("/api/questions/{$question->id}/reponse-acceptee")->assertStatus(403);

    expect($question->fresh()?->isResolved())->toBeTrue();
});

test('on ne peut pas accepter une réponse d\'une autre question, supprimée ou inexistante', function (string $case) {
    $question = Question::factory()->for($this->awa, 'author')->create();

    $answerId = match ($case) {
        'autre question' => Answer::factory()->create()->id,
        'supprimée' => tap(Answer::factory()->for($question)->create())->delete()->id,
        'inexistante' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    };

    $this->withToken($this->awaToken)->putJson("/api/questions/{$question->id}/reponse-acceptee", ['reponse_id' => $answerId])
        ->assertStatus(422)
        ->assertJsonPath('code', 'reponse_hors_question');

    expect($question->fresh()?->isResolved())->toBeFalse();
})->with(['autre question', 'supprimée', 'inexistante']);

test('retirer la réponse acceptée, ou la supprimer, rend la question non résolue', function () {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $answer = Answer::factory()->for($question)->for($this->moussa, 'author')->create();
    $question->update(['accepted_answer_id' => $answer->id]);

    $this->withToken($this->awaToken)->deleteJson("/api/questions/{$question->id}/reponse-acceptee")
        ->assertOk()
        ->assertJsonPath('data.resolue', false);

    // On remet la réponse acceptée directement en base (l'objet $question en mémoire est périmé),
    // et on vérifie qu'elle y est bien avant de supprimer la réponse.
    Question::query()->whereKey($question->id)->update(['accepted_answer_id' => $answer->id]);
    expect($question->fresh()?->isResolved())->toBeTrue();

    actingWithToken($this->moussaToken)->deleteJson("/api/reponses/{$answer->id}")->assertNoContent();

    expect($question->fresh()?->accepted_answer_id)->toBeNull();

    $this->getJson("/api/questions/{$question->id}")
        ->assertOk()
        ->assertJsonPath('data.resolue', false)
        ->assertJsonPath('data.reponse_acceptee_id', null)
        ->assertJsonCount(0, 'data.reponses');
});

// --- Voter « Utile » ---

test('un vote « Utile » compte une seule fois par compte, et peut être retiré', function () {
    $answer = Answer::factory()->for($this->moussa, 'author')->create();

    $this->withToken($this->awaToken)->putJson("/api/reponses/{$answer->id}/vote-utile")
        ->assertOk()
        ->assertExactJson(['data' => ['reponse_id' => $answer->id, 'nb_utile' => 1, 'vote_par_moi' => true]]);

    // Voter une seconde fois ne change rien.
    $this->withToken($this->awaToken)->putJson("/api/reponses/{$answer->id}/vote-utile")
        ->assertOk()
        ->assertJsonPath('data.nb_utile', 1);

    expect(DB::table('answer_votes')->count())->toBe(1);

    // La question indique au votant qu'il a voté.
    $this->withToken($this->awaToken)->getJson("/api/questions/{$answer->question_id}")
        ->assertJsonPath('data.reponses.0.vote_par_moi', true)
        ->assertJsonPath('data.reponses.0.nb_utile', 1);

    $this->withToken($this->awaToken)->deleteJson("/api/reponses/{$answer->id}/vote-utile")
        ->assertOk()
        ->assertExactJson(['data' => ['reponse_id' => $answer->id, 'nb_utile' => 0, 'vote_par_moi' => false]]);

    // Retirer un vote qui n'existe plus ne provoque pas d'erreur.
    $this->withToken($this->awaToken)->deleteJson("/api/reponses/{$answer->id}/vote-utile")->assertOk();
});

test('le vote d\'un compte n\'apparaît pas comme le vote d\'un autre', function () {
    $answer = Answer::factory()->create();
    $answer->voters()->attach($this->awa->id, ['created_at' => now()]);

    $this->withToken($this->moussaToken)->getJson("/api/questions/{$answer->question_id}")
        ->assertJsonPath('data.reponses.0.nb_utile', 1)
        ->assertJsonPath('data.reponses.0.vote_par_moi', false);
});

test('on ne vote pas pour sa propre réponse', function () {
    $answer = Answer::factory()->for($this->awa, 'author')->create();

    $this->withToken($this->awaToken)->putJson("/api/reponses/{$answer->id}/vote-utile")
        ->assertStatus(403)
        ->assertJsonPath('code', 'vote_sur_sa_reponse');

    expect(DB::table('answer_votes')->count())->toBe(0);
});

// --- Qui peut participer ---

test('participer exige un jeton de session : sans jeton 401, avec un jeton IA 403', function (string $method, string $uri) {
    $question = Question::factory()->for($this->awa, 'author')->create();
    $answer = Answer::factory()->for($question)->for($this->moussa, 'author')->create();

    $uri = str_replace(['{question}', '{reponse}'], [$question->id, $answer->id], $uri);
    $payload = [...validQuestion(), 'reponse_id' => $answer->id];

    $this->json($method, $uri, $payload)->assertStatus(401);

    // Un jeton d'agent IA ne pose pas de questions, ne répond pas et ne vote pas.
    actingWithToken(agentTokenFor($this->awa))->json($method, $uri, $payload)
        ->assertStatus(403)
        ->assertJsonPath('code', 'capacite_manquante');

    expect(Question::query()->count())->toBe(1)
        ->and(Answer::query()->count())->toBe(1)
        ->and(DB::table('answer_votes')->count())->toBe(0)
        ->and($question->fresh()?->isResolved())->toBeFalse();
})->with([
    'poser une question' => ['POST', '/api/questions'],
    'modifier une question' => ['PATCH', '/api/questions/{question}'],
    'supprimer une question' => ['DELETE', '/api/questions/{question}'],
    'répondre' => ['POST', '/api/questions/{question}/reponses'],
    'modifier une réponse' => ['PATCH', '/api/reponses/{reponse}'],
    'supprimer une réponse' => ['DELETE', '/api/reponses/{reponse}'],
    'accepter une réponse' => ['PUT', '/api/questions/{question}/reponse-acceptee'],
    'retirer la réponse acceptée' => ['DELETE', '/api/questions/{question}/reponse-acceptee'],
    'voter' => ['PUT', '/api/reponses/{reponse}/vote-utile'],
    'retirer son vote' => ['DELETE', '/api/reponses/{reponse}/vote-utile'],
]);

test('un compte suspendu ne peut plus publier', function () {
    $this->awa->forceFill(['suspended_at' => now()])->save();

    $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion())
        ->assertStatus(403)
        ->assertJsonPath('code', 'compte_suspendu');
});

test('les publications sont limitées par minute et par compte', function () {
    config()->set('samacloud.community.posts_per_minute', 2);

    $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion())->assertCreated();
    $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion())->assertCreated();
    $this->withToken($this->awaToken)->postJson('/api/questions', validQuestion())->assertStatus(429);

    // Le compteur est celui du compte : ouvrir une autre session ne le remet pas à zéro.
    actingWithToken(sessionTokenFor($this->awa))->postJson('/api/questions', validQuestion())->assertStatus(429);

    // La limite d'un compte ne bloque pas les autres.
    actingWithToken($this->moussaToken)->postJson('/api/questions', validQuestion())->assertCreated();
});
