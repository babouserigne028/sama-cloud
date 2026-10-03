<?php

declare(strict_types=1);

namespace App\Application\Community\Queries;

use App\Application\Shared\LikePattern;
use App\Domain\Community\Enums\Availability;
use App\Models\Profile;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche de profils publics : « trouver le bon profil pour un projet ».
 */
final class SearchProfiles
{
    /**
     * Les comptes suspendus n'apparaissent jamais.
     *
     * @param  string|null  $search  Texte cherché dans le nom, le pseudo et la phrase de présentation.
     * @param  string|null  $countryCode  Code pays sur 2 lettres.
     * @param  string|null  $technologySlug  Ne garder que les profils qui ont cette compétence.
     * @return LengthAwarePaginator<int, Profile>
     */
    public function handle(?string $search, ?string $countryCode, ?string $technologySlug, ?Availability $availability, int $perPage): LengthAwarePaginator
    {
        $query = Profile::query()
            ->with(['user', 'technologies'])
            ->withSum('reputationEvents as points', 'points')
            ->whereHas('user', function (Builder $user) use ($countryCode): void {
                $user->whereNull('suspended_at');

                if ($countryCode !== null) {
                    $user->where('country_code', $countryCode);
                }
            });

        if ($search !== null && $search !== '') {
            $pattern = LikePattern::contains($search);

            $query->where(function (Builder $where) use ($pattern): void {
                $where->whereLike('username', $pattern)
                    ->orWhereLike('headline', $pattern)
                    ->orWhereHas('user', fn (Builder $user) => $user->whereLike('name', $pattern));
            });
        }

        if ($technologySlug !== null) {
            $query->whereHas('technologies', fn (Builder $technology) => $technology->where('slug', $technologySlug));
        }

        if ($availability !== null) {
            $query->where('availability', $availability);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('user_id')
            ->paginate($perPage);
    }
}
