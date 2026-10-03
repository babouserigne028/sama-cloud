<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Donne ou retire le rôle administrateur à un compte existant.
 *
 * Le rôle ne peut jamais être obtenu par l'API : seul quelqu'un qui a accès
 * au serveur peut lancer cette commande. Le compte doit d'abord s'inscrire normalement.
 *
 *   php artisan samacloud:admin awa@exemple.sn
 *   php artisan samacloud:admin awa@exemple.sn --retirer
 */
#[Signature('samacloud:admin {email : Adresse e-mail du compte} {--retirer : Retire le rôle administrateur au lieu de le donner}')]
#[Description('Donne ou retire le rôle administrateur à un compte existant')]
final class SetAdminRole extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("Aucun compte avec l'adresse {$email}. Le compte doit d'abord s'inscrire.");

            return self::FAILURE;
        }

        $role = $this->option('retirer') ? UserRole::Client : UserRole::Admin;

        // Le rôle n'est pas remplissable en masse : on le fixe explicitement ici.
        $user->forceFill(['role' => $role])->save();

        $this->components->info("Le compte {$email} a maintenant le rôle « {$role->value} ».");

        return self::SUCCESS;
    }
}
