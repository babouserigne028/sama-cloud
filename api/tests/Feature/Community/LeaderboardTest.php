<?php

declare(strict_types=1);

use App\Application\Community\ReputationLedger;
use App\Domain\Community\Enums\ReputationReason;
use App\Models\Answer;
use App\Models\Question;
use App\Models\ReputationEvent;
use App\Models\User;

/*
 * Classement des développeurs et anti-triche sur les points.
 */

/**
 * Crée un développeur avec un total de points donné.
 */
function developerWithPoints(string $name, string $country, int $points, array $account = []): User
{
    $user = User::factory()->create(['name' => $name, 'country_code' => $country, ...$account]);

    if ($points > 0) {
        ReputationEvent::query()->create([
            'user_id' => $user->id,
            'reason' => ReputationReason::AnswerAccepted,
            'points' => $points,
            'subject_type' => ReputationLedger::ANSWER,
            'subject_id' => 'test-'.$user->id,
        ]);
    }

    return $user;
}

test('le classement est public et range les développeurs par points, les ex æquo au même rang', function () {
    developerWithPoints('Awa Diop', 'SN', 40);
    developerWithPoints('Moussa Koné', 'CI', 55);
    developerWithPoints('Fatou Traoré', 'ML', 40);
    developerWithPoints('Omar Sy', 'SN', 5);

    $response = $this->getJson('/api/classement');

    $response->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.total', 4);

    $rows = collect($response->json('data'))->map(fn (array $row) => [$row['rang'], $row['points'], $row['profil']['pseudo']])->all();

    expect($rows)->toBe([
        [1, 55, 'moussa-kone'],
        [2, 40, 'awa-diop'],
        [2, 40, 'fatou-traore'],
        // Après deux ex æquo au rang 2, le suivant est 4e.
        [4, 5, 'omar-sy'],
    ]);

    expect($response->json('data.0.profil'))->toHaveKeys(['pseudo', 'nom', 'pays', 'titre'])
        ->and($response->getContent())->not->toContain('@');
});

test('avec un pays, le rang est celui du classement de ce pays', function () {
    developerWithPoints('Moussa Koné', 'CI', 55);
    developerWithPoints('Awa Diop', 'SN', 40);
    developerWithPoints('Omar Sy', 'SN', 5);

    $response = $this->getJson('/api/classement?pays=sn');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.rang', 1)
        ->assertJsonPath('data.0.profil.pseudo', 'awa-diop')
        ->assertJsonPath('data.1.rang', 2)
        ->assertJsonPath('data.1.profil.pseudo', 'omar-sy');

    $this->getJson('/api/classement?pays=TG')->assertOk()->assertJsonCount(0, 'data');
});

test('les développeurs sans point et les comptes suspendus n\'apparaissent pas', function () {
    developerWithPoints('Awa Diop', 'SN', 40);
    developerWithPoints('Sans Points', 'SN', 0);
    developerWithPoints('Compte Suspendu', 'SN', 500, ['suspended_at' => now()]);

    $this->getJson('/api/classement')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rang', 1)
        ->assertJsonPath('data.0.profil.pseudo', 'awa-diop');
});

test('le classement du mois ne compte que les points gagnés depuis le début du mois', function () {
    $this->travelTo('2026-10-15 12:00:00');

    $awa = developerWithPoints('Awa Diop', 'SN', 0);
    $moussa = developerWithPoints('Moussa Koné', 'CI', 0);

    // Moussa a beaucoup de points anciens ; Awa en a gagné moins, mais ce mois-ci.
    ReputationEvent::query()->forceCreate(['user_id' => $moussa->id, 'reason' => 'reponse_acceptee', 'points' => 100, 'subject_type' => 'reponse', 'subject_id' => 'ancien', 'created_at' => '2026-09-30 23:59:59']);
    ReputationEvent::query()->forceCreate(['user_id' => $moussa->id, 'reason' => 'vote_utile', 'points' => 5, 'subject_type' => 'reponse', 'subject_id' => 'recent', 'created_at' => '2026-10-01 00:00:00']);
    ReputationEvent::query()->forceCreate(['user_id' => $awa->id, 'reason' => 'reponse_acceptee', 'points' => 15, 'subject_type' => 'reponse', 'subject_id' => 'recent-awa', 'created_at' => '2026-10-10 08:00:00']);

    $this->getJson('/api/classement')
        ->assertJsonPath('data.0.profil.pseudo', 'moussa-kone')
        ->assertJsonPath('data.0.points', 105);

    $this->getJson('/api/classement?periode=mois')
        ->assertOk()
        ->assertJsonPath('data.0.profil.pseudo', 'awa-diop')
        ->assertJsonPath('data.0.points', 15)
        ->assertJsonPath('data.1.profil.pseudo', 'moussa-kone')
        ->assertJsonPath('data.1.points', 5);
});

test('le rang continue d\'une page à l\'autre, et les filtres invalides sont refusés', function () {
    foreach ([50, 40, 30] as $index => $points) {
        developerWithPoints("Développeur {$index}", 'SN', $points);
    }

    $this->getJson('/api/classement?par_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rang', 3)
        ->assertJsonPath('data.0.points', 30)
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/classement?pays=SEN')->assertStatus(422);
    $this->getJson('/api/classement?periode=annee')->assertStatus(422);
    $this->getJson('/api/classement?par_page=51')->assertStatus(422);
});

test('le classement reflète les vrais gestes de la communauté', function () {
    $awa = User::factory()->create(['name' => 'Awa Diop', 'country_code' => 'SN']);
    $moussa = User::factory()->create(['name' => 'Moussa Koné', 'country_code' => 'CI']);
    $fatou = User::factory()->create(['name' => 'Fatou Traoré', 'country_code' => 'ML']);
    $question = Question::factory()->for($awa, 'author')->create();
    $moussaAnswer = Answer::factory()->for($question)->for($moussa, 'author')->create();
    $fatouAnswer = Answer::factory()->for($question)->for($fatou, 'author')->create();

    $this->withToken(sessionTokenFor($awa))->putJson("/api/questions/{$question->id}/reponse-acceptee", ['reponse_id' => $moussaAnswer->id])->assertOk();
    $this->withToken(sessionTokenFor($awa))->putJson("/api/reponses/{$fatouAnswer->id}/vote-utile")->assertOk();

    $this->getJson('/api/classement')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.profil.pseudo', 'moussa-kone')
        ->assertJsonPath('data.0.points', 15)
        ->assertJsonPath('data.1.profil.pseudo', 'fatou-traore')
        ->assertJsonPath('data.1.points', 5);
});

// --- Anti-triche : comptes récents ---

test('le vote ou l\'acceptation d\'un compte tout juste créé ne rapporte pas de points', function () {
    config()->set('samacloud.community.min_account_age_hours_for_points', 24);

    $newAccount = User::factory()->create(['name' => 'Compte Neuf']);
    $author = User::factory()->create(['name' => 'Moussa Koné']);
    $question = Question::factory()->for($newAccount, 'author')->create();
    $answer = Answer::factory()->for($question)->for($author, 'author')->create();
    $token = sessionTokenFor($newAccount);

    // Les gestes sont bien enregistrés…
    $this->withToken($token)->putJson("/api/reponses/{$answer->id}/vote-utile")->assertOk()->assertJsonPath('data.nb_utile', 1);
    $this->withToken($token)->putJson("/api/questions/{$question->id}/reponse-acceptee", ['reponse_id' => $answer->id])
        ->assertOk()
        ->assertJsonPath('data.resolue', true);

    // … mais ils ne rapportent rien, et l'auteur n'entre pas au classement.
    expect(app(ReputationLedger::class)->pointsOf($author->id))->toBe(0);

    $this->getJson('/api/classement')->assertJsonCount(0, 'data');
});

test('les mêmes gestes rapportent des points dès que le compte a l\'âge requis', function () {
    config()->set('samacloud.community.min_account_age_hours_for_points', 24);

    $voter = User::factory()->create();
    $author = User::factory()->create();
    $answer = Answer::factory()->for($author, 'author')->create();

    // Une minute avant les 24 heures : toujours rien.
    $this->travel(24 * 60 - 1)->minutes();
    $this->withToken(sessionTokenFor($voter))->putJson("/api/reponses/{$answer->id}/vote-utile")->assertOk();

    expect(app(ReputationLedger::class)->pointsOf($author->id))->toBe(0);

    // Passé 24 heures, un autre votant du même âge fait gagner des points.
    $this->travel(2)->minutes();
    $secondVoter = User::factory()->create();
    $secondVoter->forceFill(['created_at' => now()->subDays(2)])->save();

    forgetAuthenticatedUser();
    $this->withToken(sessionTokenFor($secondVoter))->putJson("/api/reponses/{$answer->id}/vote-utile")->assertOk();

    expect(app(ReputationLedger::class)->pointsOf($author->id))->toBe(5);
});
