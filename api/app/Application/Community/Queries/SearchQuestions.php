<?php

declare(strict_types=1);

namespace App\Application\Community\Queries;

use App\Application\Shared\LikePattern;
use App\Models\Question;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Liste et recherche des questions de la communauté.
 */
final class SearchQuestions
{
    /**
     * @param  string|null  $search  Texte cherché dans le titre et le contenu.
     * @param  string|null  $technologySlug  Ne garder que les questions de cette technologie.
     * @param  bool|null  $resolved  true = seulement les questions résolues ; false = seulement les non résolues.
     * @return LengthAwarePaginator<int, Question>
     */
    public function handle(?string $search, ?string $technologySlug, ?bool $resolved, int $perPage): LengthAwarePaginator
    {
        $query = Question::query()
            ->with(['author.profile', 'technologies'])
            ->withCount('answers');

        if ($search !== null && $search !== '') {
            $pattern = LikePattern::contains($search);

            $query->where(function (Builder $where) use ($pattern): void {
                $where->whereLike('title', $pattern)->orWhereLike('body', $pattern);
            });
        }

        if ($technologySlug !== null) {
            $query->whereHas('technologies', fn (Builder $technology) => $technology->where('slug', $technologySlug));
        }

        if ($resolved === true) {
            $query->whereNotNull('accepted_answer_id');
        } elseif ($resolved === false) {
            $query->whereNull('accepted_answer_id');
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
