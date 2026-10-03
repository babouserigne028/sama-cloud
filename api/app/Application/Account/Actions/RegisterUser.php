<?php

declare(strict_types=1);

namespace App\Application\Account\Actions;

use App\Application\Community\Actions\CreateProfile;
use App\Domain\Account\Exceptions\EmailAlreadyUsed;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée le compte d'un nouveau développeur, avec son profil public.
 */
final class RegisterUser
{
    public function __construct(private readonly CreateProfile $createProfile) {}

    /**
     * Le rôle n'est pas un paramètre : un compte créé ici est toujours un client.
     *
     * @throws EmailAlreadyUsed si l'adresse vient d'être prise par une autre inscription.
     */
    public function handle(string $name, string $email, string $password, ?string $countryCode): User
    {
        // Le compte et son profil sont créés ensemble : jamais de compte sans profil.
        return DB::transaction(function () use ($name, $email, $password, $countryCode): User {
            try {
                // Transaction imbriquée : un doublon d'adresse n'annule que cette requête.
                $user = DB::transaction(fn (): User => User::query()->create([
                    'name' => $name,
                    // Adresse enregistrée en minuscules : « Awa@X.sn » et « awa@x.sn » sont le même compte.
                    'email' => Str::lower($email),
                    // Le mot de passe est haché automatiquement par le modèle (Argon2id).
                    'password' => $password,
                    'country_code' => $countryCode,
                ]));
            } catch (UniqueConstraintViolationException) {
                throw new EmailAlreadyUsed;
            }

            $this->createProfile->handle($user);

            return $user;
        });
    }
}
