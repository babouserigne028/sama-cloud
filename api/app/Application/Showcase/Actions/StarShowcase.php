<?php

declare(strict_types=1);

namespace App\Application\Showcase\Actions;

use App\Application\Community\ReputationLedger;
use App\Domain\Community\Enums\ReputationReason;
use App\Domain\Showcase\Exceptions\CannotStarOwnShowcase;
use App\Models\Showcase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Étoile sur un projet de la vitrine : une seule par compte et par projet.
 * Chaque étoile rapporte des points au propriétaire du projet.
 */
final class StarShowcase
{
    public function __construct(private readonly ReputationLedger $reputation) {}

    /**
     * Donner deux fois une étoile ne change rien.
     *
     * @throws CannotStarOwnShowcase si le compte étoile son propre projet.
     */
    public function handle(Showcase $showcase, User $user): void
    {
        if ($showcase->user_id === $user->id) {
            throw new CannotStarOwnShowcase;
        }

        DB::transaction(function () use ($showcase, $user): void {
            DB::table('showcase_stars')->insertOrIgnore([
                'showcase_id' => $showcase->id,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            $this->reputation->award(
                $showcase->user_id,
                ReputationReason::ShowcaseStarred,
                $showcase->id,
                $user->id,
                ReputationLedger::SHOWCASE,
            );
        });
    }

    /**
     * Retirer une étoile qui n'existe pas ne change rien.
     */
    public function remove(Showcase $showcase, User $user): void
    {
        DB::transaction(function () use ($showcase, $user): void {
            $showcase->stargazers()->detach($user->id);

            $this->reputation->revoke(ReputationReason::ShowcaseStarred, $showcase->id, $user->id, ReputationLedger::SHOWCASE);
        });
    }
}
