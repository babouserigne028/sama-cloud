<?php

declare(strict_types=1);

use App\Domain\Account\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Rôle administrateur : donné seulement par une commande lancée sur le serveur,
 * et utilisable seulement avec un jeton de session.
 */

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'account.active', 'admin'])
        ->get('api/_test/admin', fn () => ['ok' => true]);
});

test('la commande donne le rôle administrateur à un compte existant', function () {
    $user = User::factory()->create(['email' => 'awa@exemple.sn']);

    $this->artisan('samacloud:admin', ['email' => 'AWA@exemple.sn'])->assertSuccessful();

    expect($user->fresh()?->role)->toBe(UserRole::Admin);
});

test('la commande peut retirer le rôle administrateur', function () {
    $admin = User::factory()->admin()->create(['email' => 'awa@exemple.sn']);

    $this->artisan('samacloud:admin', ['email' => 'awa@exemple.sn', '--retirer' => true])->assertSuccessful();

    expect($admin->fresh()?->role)->toBe(UserRole::Client);
});

test('la commande échoue pour une adresse inconnue et ne crée aucun compte', function () {
    $this->artisan('samacloud:admin', ['email' => 'inconnu@exemple.sn'])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('un administrateur connecté par le back-office accède aux routes d\'administration', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken(sessionTokenFor($admin))->getJson('/api/_test/admin')->assertOk();
});

test('un client n\'accède pas aux routes d\'administration', function () {
    $client = User::factory()->create();

    $this->withToken(sessionTokenFor($client))->getJson('/api/_test/admin')
        ->assertStatus(403)
        ->assertJsonPath('code', 'acces_refuse');
});

test('le jeton IA d\'un administrateur n\'a jamais les droits d\'administration', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken(agentTokenFor($admin))->getJson('/api/_test/admin')
        ->assertStatus(403)
        ->assertJsonPath('code', 'acces_refuse');
});

test('un administrateur suspendu ou non connecté est refusé', function () {
    $suspended = User::factory()->admin()->suspended()->create();

    $this->getJson('/api/_test/admin')->assertStatus(401);

    $this->withToken(sessionTokenFor($suspended))->getJson('/api/_test/admin')
        ->assertStatus(403)
        ->assertJsonPath('code', 'compte_suspendu');
});
