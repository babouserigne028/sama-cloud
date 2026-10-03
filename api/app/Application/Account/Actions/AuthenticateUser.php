<?php

declare(strict_types=1);

namespace App\Application\Account\Actions;

use App\Domain\Account\Exceptions\AccountSuspended;
use App\Domain\Account\Exceptions\InvalidCredentials;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Vérifie l'adresse e-mail et le mot de passe d'un développeur.
 */
final class AuthenticateUser
{
    /**
     * @throws InvalidCredentials si l'adresse ou le mot de passe est faux.
     * @throws AccountSuspended si le compte est suspendu.
     */
    public function handle(string $email, string $password): User
    {
        $user = User::query()->where('email', Str::lower($email))->first();

        if ($user === null) {
            // On hache quand même le mot de passe reçu : la réponse prend le même temps
            // que pour un compte existant, donc on ne peut pas deviner quels comptes existent.
            Hash::make($password);

            throw new InvalidCredentials;
        }

        if (! Hash::check($password, $user->password)) {
            throw new InvalidCredentials;
        }

        // Vérifié après le mot de passe : seul le vrai propriétaire apprend que son compte est suspendu.
        if ($user->isSuspended()) {
            throw new AccountSuspended;
        }

        return $user;
    }
}
