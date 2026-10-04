<?php

declare(strict_types=1);

namespace App\Application\Showcase\Queries;

use App\Application\Shared\LikePattern;
use App\Domain\Showcase\Enums\ImportStatus;
use App\Models\Showcase;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Liste et recherche des projets de la vitrine.
 */
final class SearchShowcases
{
    /**
     * Seuls les projets dont le code est disponible sont listés : un import en cours
     * ou en échec n'apparaît pas dans la vitrine publique.
     *
     * @param  string|null  $search  Texte cherché dans le titre et la description.
     * @param  bool  $mostStarredFirst  true = les projets les plus étoilés d'abord ; false = les plus récents d'abord.
     * @return LengthAwarePaginator<int, Showcase>
     */
    public function handle(?string $search, ?string $technologySlug, bool $mostStarredFirst, int $perPage): LengthAwarePaginator
    {
        $query = Showcase::query()
            ->with(['owner.profile', 'technologies'])
            ->withCount(['stargazers', 'comments'])
            ->where('import_status', ImportStatus::Done);

        if ($search !== null && $search !== '') {
            $pattern = LikePattern::contains($search);

            $query->where(function (Builder $where) use ($pattern): void {
                $where->whereLike('title', $pattern)->orWhereLike('description', $pattern);
            });
        }

        if ($technologySlug !== null) {
            $query->whereHas('technologies', fn (Builder $technology) => $technology->where('slug', $technologySlug));
        }

        if ($mostStarredFirst) {
            $query->orderByDesc('stargazers_count');
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
