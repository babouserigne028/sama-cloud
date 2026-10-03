<?php

declare(strict_types=1);

use App\Application\Community\ReputationLedger;
use App\Domain\Community\Enums\ReportStatus;
use App\Domain\Community\Enums\ReputationReason;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

/*
 * Autour des questions-réponses : pagination des réponses, points de réputation,
 * notifications et signalements.
 */

beforeEach(function () {
    $this->awa = User::factory()->create(['name' => 'Awa Diop']);
    $this->moussa = User::factory()->create(['name' => 'Moussa Koné']);
    $this->fatou = User::factory()->create(['name' => 'Fatou Traoré']);
    $this->awaToken = sessionTokenFor($this->awa);
    $this->moussaToken = sessionTokenFor($this->moussa);
    $this->fatouToken = sessionTokenFor($this->fatou);

    // Question d'Awa, avec une réponse de Moussa.
    $this->question = Question::factory()->for($this->awa, 'author')->create();
    $this->answer = Answer::factory()->for($this->question)->for($this->moussa, 'author')->create();
});

function switchTo(string $token): TestCase
{
    forgetAuthenticatedUser();

    return test()->withToken($token);
}

function pointsOf(User $user): int
{
    return app(ReputationLedger::class)->pointsOf($user->id);
}

// --- Pagination des réponses ---

test('une question ne joint que ses 20 premières réponses, les suivantes se lisent page par page', function () {
    Answer::factory()->for($this->question)->count(24)->create();

    $detail = $this->getJson("/api/questions/{$this->question->id}");

    $detail->assertOk()->assertJsonPath('data.nb_reponses', 25)->assertJsonCount(20, 'data.reponses');

    $secondPage = $this->getJson("/api/questions/{$this->question->id}/reponses?page=2");

    $secondPage->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2);

    // Aucune réponse n'est perdue ni répétée entre les deux pages.
    $firstIds = collect($detail->json('data.reponses'))->pluck('id');
    $secondIds = collect($secondPage->json('data'))->pluck('id');

    expect($firstIds->merge($secondIds)->unique()->count())->toBe(25);
});

test('la liste des réponses garde l\'ordre : acceptée, puis les plus utiles, puis les plus anciennes', function () {
    $useful = Answer::factory()->for($this->question)->create(['created_at' => now()->subHour()]);
    $accepted = Answer::factory()->for($this->question)->create();
    $useful->voters()->attach($this->fatou->id, ['created_at' => now()]);
    $this->question->update(['accepted_answer_id' => $accepted->id]);
    // « created_at » n'est pas modifiable en masse : on le fixe de force pour le test.
    $this->answer->forceFill(['created_at' => now()->subDay()])->save();

    $response = $this->withToken($this->fatouToken)->getJson("/api/questions/{$this->question->id}/reponses");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $accepted->id)
        ->assertJsonPath('data.0.acceptee', true)
        ->assertJsonPath('data.1.id', $useful->id)
        ->assertJsonPath('data.1.vote_par_moi', true)
        ->assertJsonPath('data.2.id', $this->answer->id)
        ->assertJsonPath('data.2.vote_par_moi', false);
});

test('les réponses d\'une question inconnue ou supprimée sont introuvables, et la page doit être valide', function () {
    $this->getJson("/api/questions/{$this->question->id}/reponses?page=0")->assertStatus(422);
    $this->getJson('/api/questions/01ARZ3NDEKTSV4RRFFQ69G5FAV/reponses')->assertStatus(404);

    $this->question->delete();

    $this->getJson("/api/questions/{$this->question->id}/reponses")->assertStatus(404);
});

// --- Points de réputation ---

test('une réponse acceptée rapporte 15 points à son auteur, visibles sur son profil', function () {
    $this->withToken($this->awaToken)->putJson("/api/questions/{$this->question->id}/reponse-acceptee", ['reponse_id' => $this->answer->id])
        ->assertOk();

    expect(pointsOf($this->moussa))->toBe(15)->and(pointsOf($this->awa))->toBe(0);

    $this->getJson('/api/profils/moussa-kone')->assertOk()->assertJsonPath('data.points', 15);
    $this->getJson('/api/profils/awa-diop')->assertOk()->assertJsonPath('data.points', 0);

    // Accepter de nouveau la même réponse ne donne pas les points deux fois.
    $this->withToken($this->awaToken)->putJson("/api/questions/{$this->question->id}/reponse-acceptee", ['reponse_id' => $this->answer->id])
        ->assertOk();

    expect(pointsOf($this->moussa))->toBe(15);
});

test('choisir une autre réponse déplace les points, et retirer le choix les reprend', function () {
    $fatouAnswer = Answer::factory()->for($this->question)->for($this->fatou, 'author')->create();
    $url = "/api/questions/{$this->question->id}/reponse-acceptee";

    $this->withToken($this->awaToken)->putJson($url, ['reponse_id' => $this->answer->id])->assertOk();
    $this->withToken($this->awaToken)->putJson($url, ['reponse_id' => $fatouAnswer->id])->assertOk();

    expect(pointsOf($this->moussa))->toBe(0)->and(pointsOf($this->fatou))->toBe(15);

    $this->withToken($this->awaToken)->deleteJson($url)->assertOk();

    expect(pointsOf($this->fatou))->toBe(0);
});

test('accepter sa propre réponse est permis mais ne rapporte aucun point', function () {
    $ownAnswer = Answer::factory()->for($this->question)->for($this->awa, 'author')->create();

    $this->withToken($this->awaToken)->putJson("/api/questions/{$this->question->id}/reponse-acceptee", ['reponse_id' => $ownAnswer->id])
        ->assertOk()
        ->assertJsonPath('data.resolue', true);

    expect(pointsOf($this->awa))->toBe(0)
        ->and($this->awa->notifications()->count())->toBe(0);
});

test('chaque vote « Utile » rapporte 5 points, une seule fois par votant, et se reprend', function () {
    $url = "/api/reponses/{$this->answer->id}/vote-utile";

    $this->withToken($this->awaToken)->putJson($url)->assertOk();
    $this->withToken($this->awaToken)->putJson($url)->assertOk();

    expect(pointsOf($this->moussa))->toBe(5);

    switchTo($this->fatouToken)->putJson($url)->assertOk();

    expect(pointsOf($this->moussa))->toBe(10);

    // Retirer son vote ne reprend que ses propres points.
    switchTo($this->awaToken)->deleteJson($url)->assertOk();

    expect(pointsOf($this->moussa))->toBe(5);

    $this->getJson('/api/profils?q=moussa')->assertJsonPath('data.0.points', 5);
});

test('une réponse ou une question supprimée ne rapporte plus de points', function (string $whatIsDeleted) {
    $this->answer->voters()->attach($this->fatou->id, ['created_at' => now()]);
    app(ReputationLedger::class)->award($this->moussa->id, ReputationReason::AnswerVotedUseful, $this->answer->id, $this->fatou->id);
    $this->withToken($this->awaToken)->putJson("/api/questions/{$this->question->id}/reponse-acceptee", ['reponse_id' => $this->answer->id])->assertOk();

    expect(pointsOf($this->moussa))->toBe(20);

    $whatIsDeleted === 'réponse'
        ? switchTo($this->moussaToken)->deleteJson("/api/reponses/{$this->answer->id}")->assertNoContent()
        : $this->withToken($this->awaToken)->deleteJson("/api/questions/{$this->question->id}")->assertNoContent();

    expect(pointsOf($this->moussa))->toBe(0);
})->with(['réponse', 'question']);

// --- Notifications ---

test('l\'auteur d\'une question est prévenu quand quelqu\'un répond', function () {
    $answerId = $this->withToken($this->moussaToken)
        ->postJson("/api/questions/{$this->question->id}/reponses", ['contenu' => 'Ajoutez la clé dans votre fichier .env.'])
        ->assertCreated()
        ->json('data.id');

    $response = switchTo($this->awaToken)->getJson('/api/notifications');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.non_lues', 1)
        ->assertJsonPath('data.0.code', 'reponse_recue')
        ->assertJsonPath('data.0.lue', false)
        ->assertJsonPath('data.0.donnees.question_id', $this->question->id)
        ->assertJsonPath('data.0.donnees.question_titre', $this->question->title)
        ->assertJsonPath('data.0.donnees.reponse_id', $answerId)
        ->assertJsonPath('data.0.donnees.auteur.pseudo', 'moussa-kone');

    // Celui qui répond ne reçoit rien.
    switchTo($this->moussaToken)->getJson('/api/notifications')->assertJsonCount(0, 'data')->assertJsonPath('meta.non_lues', 0);
});

test('répondre à sa propre question ne déclenche aucune notification', function () {
    $this->withToken($this->awaToken)
        ->postJson("/api/questions/{$this->question->id}/reponses", ['contenu' => 'J\'ai trouvé la solution moi-même.'])
        ->assertCreated();

    expect($this->awa->notifications()->count())->toBe(0);
});

test('l\'auteur d\'une réponse est prévenu quand elle est acceptée, une seule fois', function () {
    $url = "/api/questions/{$this->question->id}/reponse-acceptee";

    $this->withToken($this->awaToken)->putJson($url, ['reponse_id' => $this->answer->id])->assertOk();
    $this->withToken($this->awaToken)->putJson($url, ['reponse_id' => $this->answer->id])->assertOk();

    switchTo($this->moussaToken)->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'reponse_acceptee')
        ->assertJsonPath('data.0.donnees.reponse_id', $this->answer->id)
        ->assertJsonPath('data.0.donnees.points', 15);
});

test('une notification se marque comme lue, une par une ou toutes d\'un coup', function () {
    foreach (range(1, 3) as $index) {
        switchTo($this->moussaToken)->postJson("/api/questions/{$this->question->id}/reponses", ['contenu' => "Réponse numéro {$index} à la question."])->assertCreated();
    }

    $firstId = switchTo($this->awaToken)->getJson('/api/notifications')->assertJsonPath('meta.non_lues', 3)->json('data.0.id');

    $this->withToken($this->awaToken)->postJson("/api/notifications/{$firstId}/lue")->assertNoContent();

    $this->withToken($this->awaToken)->getJson('/api/notifications')
        ->assertJsonPath('meta.non_lues', 2)
        ->assertJsonPath('data.0.lue', true);

    $this->withToken($this->awaToken)->postJson('/api/notifications/lues')->assertNoContent();

    $this->withToken($this->awaToken)->getJson('/api/notifications')->assertJsonPath('meta.non_lues', 0);
});

test('on ne lit ni ne marque les notifications d\'un autre compte', function () {
    switchTo($this->moussaToken)->postJson("/api/questions/{$this->question->id}/reponses", ['contenu' => 'Une réponse pour créer une notification.'])->assertCreated();
    $notificationId = $this->awa->notifications()->sole()->id;

    switchTo($this->fatouToken)->getJson('/api/notifications')->assertJsonCount(0, 'data');
    $this->withToken($this->fatouToken)->postJson("/api/notifications/{$notificationId}/lue")->assertStatus(404);
    $this->withToken($this->fatouToken)->postJson('/api/notifications/lues')->assertNoContent();

    expect($this->awa->unreadNotifications()->count())->toBe(1);
});

test('les notifications exigent un jeton de session', function () {
    $this->getJson('/api/notifications')->assertStatus(401);

    switchTo(agentTokenFor($this->awa))->getJson('/api/notifications')
        ->assertStatus(403)
        ->assertJsonPath('code', 'capacite_manquante');
});

// --- Signalements ---

test('un membre signale une question ou une réponse, sans doublon', function () {
    $response = $this->withToken($this->fatouToken)->postJson("/api/questions/{$this->question->id}/signalements", [
        'motif' => 'spam',
        'details' => 'Publicité déguisée.',
    ]);

    $response->assertCreated()->assertJson(['data' => [
        'type' => 'question',
        'contenu_id' => $this->question->id,
        'motif' => 'spam',
        'details' => 'Publicité déguisée.',
        'statut' => 'ouvert',
    ]]);

    // Le signalement ne révèle pas qui l'a fait à celui qui le crée… ni à personne d'autre que les administrateurs.
    expect($response->json('data'))->not->toHaveKey('signale_par');

    // Signaler de nouveau la même question renvoie le même signalement.
    $again = $this->withToken($this->fatouToken)->postJson("/api/questions/{$this->question->id}/signalements", ['motif' => 'hors_sujet']);

    expect($again->json('data.id'))->toBe($response->json('data.id'))
        ->and($again->json('data.motif'))->toBe('spam');

    $this->withToken($this->fatouToken)->postJson("/api/reponses/{$this->answer->id}/signalements", ['motif' => 'contenu_offensant'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'reponse');

    expect(Report::query()->count())->toBe(2);
});

test('un signalement invalide est refusé', function (array $payload, string $invalidField) {
    $response = $this->withToken($this->fatouToken)->postJson("/api/questions/{$this->question->id}/signalements", $payload);

    $response->assertStatus(422)->assertJsonPath('details.0.champ', $invalidField);

    expect(Report::query()->count())->toBe(0);
})->with([
    'sans motif' => [[], 'motif'],
    'motif inconnu' => [['motif' => 'je-n-aime-pas'], 'motif'],
    'motif « autre » sans précision' => [['motif' => 'autre'], 'details'],
    'détails trop longs' => [['motif' => 'spam', 'details' => str_repeat('a', 501)], 'details'],
]);

test('on ne signale ni son propre contenu, ni un contenu introuvable', function () {
    $this->withToken($this->awaToken)->postJson("/api/questions/{$this->question->id}/signalements", ['motif' => 'spam'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'signalement_de_son_contenu');

    switchTo($this->moussaToken)->postJson("/api/reponses/{$this->answer->id}/signalements", ['motif' => 'spam'])
        ->assertStatus(403);

    switchTo($this->fatouToken)->postJson('/api/questions/01ARZ3NDEKTSV4RRFFQ69G5FAV/signalements', ['motif' => 'spam'])->assertStatus(404);

    $this->question->delete();

    $this->withToken($this->fatouToken)->postJson("/api/reponses/{$this->answer->id}/signalements", ['motif' => 'spam'])->assertStatus(404);

    expect(Report::query()->count())->toBe(0);
});

test('signaler exige un jeton de session', function () {
    $this->postJson("/api/questions/{$this->question->id}/signalements", ['motif' => 'spam'])->assertStatus(401);

    switchTo(agentTokenFor($this->fatou))->postJson("/api/questions/{$this->question->id}/signalements", ['motif' => 'spam'])->assertStatus(403);

    expect(Report::query()->count())->toBe(0);
});

// --- Traitement des signalements par un administrateur ---

test('un administrateur voit les signalements ouverts, du plus ancien au plus récent, avec leur auteur', function () {
    $old = Report::query()->create(['user_id' => $this->fatou->id, 'content_type' => 'question', 'content_id' => $this->question->id, 'reason' => 'spam', 'status' => 'ouvert']);
    $old->forceFill(['created_at' => now()->subDay()])->save();
    $recent = Report::query()->create(['user_id' => $this->awa->id, 'content_type' => 'reponse', 'content_id' => $this->answer->id, 'reason' => 'hors_sujet', 'status' => 'ouvert']);
    Report::query()->create(['user_id' => $this->moussa->id, 'content_type' => 'question', 'content_id' => $this->question->id, 'reason' => 'spam', 'status' => 'rejete']);

    $adminToken = sessionTokenFor(User::factory()->admin()->create());

    $this->withToken($adminToken)->getJson('/api/admin/signalements')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $old->id)
        ->assertJsonPath('data.0.signale_par.pseudo', 'fatou-traore')
        ->assertJsonPath('data.1.id', $recent->id);

    $this->withToken($adminToken)->getJson('/api/admin/signalements?statut=rejete')->assertJsonCount(1, 'data');
    $this->withToken($adminToken)->getJson('/api/admin/signalements?statut=nimporte')->assertStatus(422);
});

test('retirer un contenu signalé le supprime, reprend ses points et règle tous ses signalements', function () {
    $this->withToken($this->awaToken)->putJson("/api/questions/{$this->question->id}/reponse-acceptee", ['reponse_id' => $this->answer->id])->assertOk();
    $first = Report::query()->create(['user_id' => $this->fatou->id, 'content_type' => 'reponse', 'content_id' => $this->answer->id, 'reason' => 'spam', 'status' => 'ouvert']);
    $second = Report::query()->create(['user_id' => $this->awa->id, 'content_type' => 'reponse', 'content_id' => $this->answer->id, 'reason' => 'spam', 'status' => 'ouvert']);
    $admin = User::factory()->admin()->create();

    switchTo(sessionTokenFor($admin))->postJson("/api/admin/signalements/{$first->id}/decision", ['decision' => 'retirer'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'retenu');

    expect(Answer::query()->find($this->answer->id))->toBeNull()
        ->and($this->question->fresh()?->isResolved())->toBeFalse()
        ->and(pointsOf($this->moussa))->toBe(0)
        ->and($second->fresh()?->status)->toBe(ReportStatus::Upheld)
        ->and($second->fresh()?->handled_by)->toBe($admin->id);
});

test('rejeter un signalement laisse le contenu en ligne, et un signalement ne se traite qu\'une fois', function () {
    $report = Report::query()->create(['user_id' => $this->fatou->id, 'content_type' => 'question', 'content_id' => $this->question->id, 'reason' => 'spam', 'status' => 'ouvert']);
    $adminToken = sessionTokenFor(User::factory()->admin()->create());

    $this->withToken($adminToken)->postJson("/api/admin/signalements/{$report->id}/decision", ['decision' => 'rejeter'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'rejete');

    $this->getJson("/api/questions/{$this->question->id}")->assertOk();

    $this->withToken($adminToken)->postJson("/api/admin/signalements/{$report->id}/decision", ['decision' => 'retirer'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'signalement_deja_traite');

    expect(Question::query()->find($this->question->id))->not->toBeNull();

    $this->withToken($adminToken)->postJson("/api/admin/signalements/{$report->id}/decision", ['decision' => 'peut-etre'])->assertStatus(422);
});

test('seul un administrateur connecté par le back-office traite les signalements', function () {
    $report = Report::query()->create(['user_id' => $this->fatou->id, 'content_type' => 'question', 'content_id' => $this->question->id, 'reason' => 'spam', 'status' => 'ouvert']);
    $admin = User::factory()->admin()->create();

    $this->getJson('/api/admin/signalements')->assertStatus(401);

    foreach ([$this->awaToken, agentTokenFor($admin)] as $token) {
        switchTo($token)->getJson('/api/admin/signalements')->assertStatus(403);
        switchTo($token)->postJson("/api/admin/signalements/{$report->id}/decision", ['decision' => 'retirer'])->assertStatus(403);
    }

    expect($report->fresh()?->status)->toBe(ReportStatus::Open)
        ->and(DB::table('questions')->whereNull('deleted_at')->count())->toBe(1);
});
