<?php

declare(strict_types=1);

use App\Models\PersonalAccessToken;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'awa@exemple.sn',
        'password' => 'motdepasse-solide-2026',
    ]);
});

test('un développeur se connecte et reçoit un jeton de session utilisable', function () {
    $response = $this->postJson('/api/connexion', [
        'email' => 'awa@exemple.sn',
        'mot_de_passe' => 'motdepasse-solide-2026',
        'appareil' => 'Chrome sur Windows',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compte.email', 'awa@exemple.sn')
        ->assertJsonPath('data.jeton.type', 'session')
        ->assertJsonPath('data.jeton.nom', 'Chrome sur Windows');

    $token = $response->json('data.jeton.valeur');

    expect($token)->toStartWith('sc_sess_');

    $this->withToken($token)->getJson('/api/moi')->assertOk();
});

test('le jeton de session expire au bout de la durée configurée', function () {
    $this->freezeTime();
    config()->set('samacloud.auth.session_token_days', 7);

    $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'motdepasse-solide-2026'])
        ->assertOk()
        ->assertJsonPath('data.jeton.nom', 'Navigateur')
        ->assertJsonPath('data.jeton.expire_le', now()->addDays(7)->toIso8601String());
});

test('l\'adresse e-mail n\'est pas sensible à la casse à la connexion', function () {
    $this->postJson('/api/connexion', ['email' => 'AWA@Exemple.SN', 'mot_de_passe' => 'motdepasse-solide-2026'])
        ->assertOk();
});

test('un mauvais mot de passe et une adresse inconnue donnent exactement la même réponse', function () {
    $wrongPassword = $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'mauvais-mot-de-passe-1']);
    $unknownEmail = $this->postJson('/api/connexion', ['email' => 'inconnu@exemple.sn', 'mot_de_passe' => 'mauvais-mot-de-passe-1']);

    $expected = [
        'code' => 'identifiants_invalides',
        'message' => 'Adresse e-mail ou mot de passe incorrect.',
    ];

    $wrongPassword->assertStatus(401)->assertExactJson($expected);
    $unknownEmail->assertStatus(401)->assertExactJson($expected);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('un compte suspendu ne peut pas se connecter, même avec le bon mot de passe', function () {
    $this->user->forceFill(['suspended_at' => now()])->save();

    $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'motdepasse-solide-2026'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'compte_suspendu');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('un compte suspendu avec un mauvais mot de passe ne révèle pas la suspension', function () {
    $this->user->forceFill(['suspended_at' => now()])->save();

    $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'mauvais-mot-de-passe-1'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'identifiants_invalides');
});

test('les champs obligatoires de la connexion sont vérifiés', function () {
    $response = $this->postJson('/api/connexion', []);

    $response->assertStatus(422);

    expect(collect($response->json('details'))->pluck('champ')->all())->toBe(['email', 'mot_de_passe']);
});

test('après trop d\'essais ratés, la connexion est bloquée même avec le bon mot de passe', function () {
    config()->set('samacloud.auth.attempts_per_minute', 3);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'mauvais-mot-de-passe-1'])
            ->assertStatus(401);
    }

    $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'motdepasse-solide-2026'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'trop_de_requetes');
});

test('le blocage d\'une adresse ne bloque pas les autres comptes', function () {
    config()->set('samacloud.auth.attempts_per_minute', 1);
    User::factory()->create(['email' => 'fatou@exemple.ml', 'password' => 'motdepasse-solide-2026']);

    $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'mauvais-mot-de-passe-1'])->assertStatus(401);
    $this->postJson('/api/connexion', ['email' => 'awa@exemple.sn', 'mot_de_passe' => 'mauvais-mot-de-passe-1'])->assertStatus(429);

    $this->postJson('/api/connexion', ['email' => 'fatou@exemple.ml', 'mot_de_passe' => 'motdepasse-solide-2026'])->assertOk();
});
