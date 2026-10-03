<?php

declare(strict_types=1);

use App\Domain\Account\Enums\TokenKind;
use App\Domain\Account\Enums\UserRole;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function validRegistration(array $overrides = []): array
{
    return [
        'nom' => 'Awa Diop',
        'email' => 'awa@exemple.sn',
        'mot_de_passe' => 'motdepasse-solide-2026',
        'mot_de_passe_confirmation' => 'motdepasse-solide-2026',
        'pays' => 'SN',
        ...$overrides,
    ];
}

test('un développeur s\'inscrit et reçoit son compte et un jeton de session', function () {
    $response = $this->postJson('/api/inscription', validRegistration());

    $response->assertCreated()
        ->assertJsonPath('data.compte.nom', 'Awa Diop')
        ->assertJsonPath('data.compte.email', 'awa@exemple.sn')
        ->assertJsonPath('data.compte.pays', 'SN')
        ->assertJsonPath('data.compte.role', 'client')
        ->assertJsonPath('data.compte.demonstration', false)
        ->assertJsonPath('data.compte.credit_fcfa', 0)
        ->assertJsonPath('data.jeton.type', 'session')
        ->assertJsonMissingPath('data.compte.password');

    expect($response->json('data.jeton.valeur'))->toStartWith('sc_sess_');

    $user = User::query()->where('email', 'awa@exemple.sn')->sole();

    expect($user->role)->toBe(UserRole::Client)
        ->and(PersonalAccessToken::query()->sole()->kind)->toBe(TokenKind::Session);
});

test('le jeton reçu à l\'inscription donne accès à l\'API', function () {
    $token = $this->postJson('/api/inscription', validRegistration())->json('data.jeton.valeur');

    $this->withToken($token)->getJson('/api/moi')
        ->assertOk()
        ->assertJsonPath('data.compte.email', 'awa@exemple.sn');
});

test('le mot de passe est haché avec Argon2id', function () {
    $this->postJson('/api/inscription', validRegistration())->assertCreated();

    $storedPassword = DB::table('users')->value('password');

    expect($storedPassword)->toStartWith('$argon2id$')
        ->and($storedPassword)->not->toContain('motdepasse-solide-2026');
});

test('l\'adresse e-mail est enregistrée en minuscules et le pays en majuscules', function () {
    $this->postJson('/api/inscription', validRegistration(['email' => '  Awa@Exemple.SN ', 'pays' => 'sn']))
        ->assertCreated()
        ->assertJsonPath('data.compte.email', 'awa@exemple.sn')
        ->assertJsonPath('data.compte.pays', 'SN');
});

test('le pays est facultatif', function () {
    $this->postJson('/api/inscription', validRegistration(['pays' => null]))
        ->assertCreated()
        ->assertJsonPath('data.compte.pays', null);
});

test('on ne peut pas s\'attribuer le rôle administrateur, du crédit ou un compte de démonstration', function () {
    $this->postJson('/api/inscription', validRegistration([
        'role' => 'administrateur',
        'credit_fcfa' => 1_000_000,
        'is_demo' => true,
        'demonstration' => true,
    ]))->assertCreated();

    $user = User::query()->sole();

    expect($user->role)->toBe(UserRole::Client)
        ->and($user->credit_fcfa)->toBe(0)
        ->and($user->is_demo)->toBeFalse();
});

test('une adresse déjà utilisée est refusée, même avec une autre casse', function () {
    User::factory()->create(['email' => 'awa@exemple.sn']);

    $this->postJson('/api/inscription', validRegistration(['email' => 'AWA@exemple.sn']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_echouee')
        ->assertJsonPath('details.0.champ', 'email')
        ->assertJsonPath('details.0.message', 'Un compte existe déjà avec cette adresse e-mail.');

    expect(User::query()->count())->toBe(1);
});

test('des données invalides sont refusées et aucun compte n\'est créé', function (array $overrides, string $invalidField) {
    $response = $this->postJson('/api/inscription', validRegistration($overrides));

    $response->assertStatus(422)->assertJsonPath('code', 'validation_echouee');

    expect(collect($response->json('details'))->pluck('champ')->all())->toContain($invalidField)
        ->and(User::query()->count())->toBe(0);
})->with([
    'nom absent' => [['nom' => ''], 'nom'],
    'e-mail mal formé' => [['email' => 'pas-un-email'], 'email'],
    'mot de passe trop court' => [['mot_de_passe' => 'court1', 'mot_de_passe_confirmation' => 'court1'], 'mot_de_passe'],
    'mot de passe sans chiffre' => [['mot_de_passe' => 'seulementdeslettres', 'mot_de_passe_confirmation' => 'seulementdeslettres'], 'mot_de_passe'],
    'mot de passe sans lettre' => [['mot_de_passe' => '12345678901234', 'mot_de_passe_confirmation' => '12345678901234'], 'mot_de_passe'],
    'confirmation différente' => [['mot_de_passe_confirmation' => 'autre-chose-2026'], 'mot_de_passe'],
    'pays sur 3 lettres' => [['pays' => 'SEN'], 'pays'],
]);

test('les inscriptions répétées pour une même adresse sont limitées', function () {
    config()->set('samacloud.auth.attempts_per_minute', 2);

    $this->postJson('/api/inscription', validRegistration(['nom' => '']))->assertStatus(422);
    $this->postJson('/api/inscription', validRegistration(['nom' => '']))->assertStatus(422);

    $this->postJson('/api/inscription', validRegistration())
        ->assertStatus(429)
        ->assertJsonPath('code', 'trop_de_requetes');
});
