<?php

declare(strict_types=1);

namespace App\Application\Account\Actions;

use App\Domain\Account\Exceptions\EmailAlreadyUsed;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Crée le compte d'un nouveau développeur.
 */
final class RegisterUser
{
    /**
     * Le rôle n'est pas un paramètre : un compte créé ici est toujours un client.
     *
     * @throws EmailAlreadyUsed si l'adresse vient d'être prise par une autre inscription.
     */
    public function handle(string $name, string $email, string $password, ?string $countryCode): User
    {
        try {
            return User::query()->create([
                'name' => $name,
                // Adresse enregistrée en minuscules : « Awa@X.sn » et « awa@x.sn » sont le même compte.
                'email' => Str::lower($email),
                // Le mot de passe est haché automatiquement par le modèle (Argon2id).
                'password' => $password,
                'country_code' => $countryCode,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new EmailAlreadyUsed;
        }
    }
}
