<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Domain\Community\Enums\Availability;
use App\Domain\Community\UsernameRules;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée le profil public d'un nouveau compte, avec un pseudo tiré de son nom.
 * Le développeur pourra changer ce pseudo ensuite.
 */
final class CreateProfile
{
    private const int MAX_ATTEMPTS = 5;

    public function handle(User $user): Profile
    {
        $base = $this->baseUsername($user->name);

        // Premier essai : le pseudo « propre ». Ensuite, on ajoute un suffixe aléatoire.
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $username = $attempt === 0 ? $base : $this->withSuffix($base);

            if (UsernameRules::isReserved($username) || Profile::query()->where('username', $username)->exists()) {
                continue;
            }

            try {
                // Transaction imbriquée : si deux inscriptions prennent le même pseudo au même
                // instant, la base refuse la seconde et on réessaie sans annuler le reste.
                return DB::transaction(fn (): Profile => Profile::query()->create([
                    'user_id' => $user->id,
                    'username' => $username,
                    'availability' => Availability::OpenToOffers,
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        // Dernier recours, toujours unique : un pseudo bâti sur l'identifiant du compte.
        return Profile::query()->create([
            'user_id' => $user->id,
            'username' => 'dev-'.$user->id.'-'.Str::lower(Str::random(4)),
            'availability' => Availability::OpenToOffers,
        ]);
    }

    /**
     * « Awa Diop » devient « awa-diop ». Un nom sans lettre ni chiffre latin donne « dev ».
     */
    private function baseUsername(string $name): string
    {
        // On garde de la place pour un éventuel suffixe de 5 caractères (« -x7k2 »).
        $slug = trim(Str::substr(Str::slug($name), 0, UsernameRules::MAX_LENGTH - 5), '-');

        return Str::length($slug) >= UsernameRules::MIN_LENGTH ? $slug : 'dev';
    }

    private function withSuffix(string $base): string
    {
        return $base.'-'.Str::lower(Str::random(4));
    }
}
