<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Domain\Community\Exceptions\UsernameAlreadyTaken;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour le profil public d'un développeur.
 *
 * Seules les clés présentes dans « $changes » sont modifiées : on peut donc
 * changer un seul champ sans renvoyer tout le profil.
 */
final class UpdateProfile
{
    /** Champs du compte que le développeur peut changer depuis son profil. */
    private const array USER_FIELDS = ['name', 'country_code'];

    /** Champs du profil. */
    private const array PROFILE_FIELDS = ['username', 'headline', 'bio', 'availability', 'github_url', 'website_url', 'linkedin_url'];

    /**
     * @param array{
     *     name?: string,
     *     country_code?: string|null,
     *     username?: string,
     *     headline?: string|null,
     *     bio?: string|null,
     *     availability?: string,
     *     github_url?: string|null,
     *     website_url?: string|null,
     *     linkedin_url?: string|null,
     *     technologies?: list<string>,
     * } $changes Données déjà validées. « technologies » est la liste complète des slugs à garder.
     *
     * @throws UsernameAlreadyTaken si le pseudo vient d'être pris par quelqu'un d'autre.
     */
    public function handle(User $user, array $changes): Profile
    {
        try {
            return DB::transaction(function () use ($user, $changes): Profile {
                $profile = Profile::query()->whereKey($user->id)->firstOrFail();

                $user->update(Arr::only($changes, self::USER_FIELDS));
                $profile->update(Arr::only($changes, self::PROFILE_FIELDS));

                if (array_key_exists('technologies', $changes)) {
                    // « sync » remplace la liste : ce qui n'est plus envoyé est retiré.
                    $profile->technologies()->sync(
                        Technology::query()->whereIn('slug', $changes['technologies'])->pluck('id'),
                    );
                }

                return $profile;
            });
        } catch (UniqueConstraintViolationException) {
            throw new UsernameAlreadyTaken;
        }
    }
}
