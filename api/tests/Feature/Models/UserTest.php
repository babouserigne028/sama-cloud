<?php

declare(strict_types=1);

use App\Domain\Account\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('un nouveau compte est un client, sans crédit, non suspendu', function () {
    $user = User::query()->create([
        'name' => 'Awa Diop',
        'email' => 'awa@exemple.sn',
        'password' => 'mot-de-passe-solide',
        'country_code' => 'SN',
    ])->fresh();

    expect($user?->role)->toBe(UserRole::Client)
        ->and($user?->isAdmin())->toBeFalse()
        ->and($user?->is_demo)->toBeFalse()
        ->and($user?->credit_fcfa)->toBe(0)
        ->and($user?->isSuspended())->toBeFalse();
});

test('le mot de passe est haché en base et absent du JSON', function () {
    $user = User::factory()->create(['password' => 'mot-de-passe-solide']);

    $storedPassword = DB::table('users')->where('id', $user->id)->value('password');

    expect($storedPassword)->not->toBe('mot-de-passe-solide')
        ->and(Hash::check('mot-de-passe-solide', $storedPassword))->toBeTrue()
        ->and($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

test('le rôle, le crédit et la suspension ne peuvent pas être fixés par un formulaire', function (string $protectedField, mixed $value) {
    expect(fn () => User::query()->create([
        'name' => 'Pirate',
        'email' => 'pirate@exemple.sn',
        'password' => 'mot-de-passe-solide',
        $protectedField => $value,
    ]))->toThrow(MassAssignmentException::class);
})->with([
    'rôle administrateur' => ['role', 'administrateur'],
    'crédit' => ['credit_fcfa', 1_000_000],
    'compte de démonstration' => ['is_demo', true],
    'suspension' => ['suspended_at', null],
]);

test('deux comptes ne peuvent pas partager la même adresse e-mail', function () {
    User::factory()->create(['email' => 'awa@exemple.sn']);

    expect(fn () => DB::transaction(fn () => User::factory()->create(['email' => 'awa@exemple.sn'])))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('les états du compte sont reconnus', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue()
        ->and(User::factory()->suspended()->create()->isSuspended())->toBeTrue()
        ->and(User::factory()->demo()->create()->is_demo)->toBeTrue();
});

test('la base refuse un crédit négatif', function () {
    expect(fn () => DB::transaction(fn () => User::factory()->create(['credit_fcfa' => -1])))
        ->toThrow(QueryException::class);
});
