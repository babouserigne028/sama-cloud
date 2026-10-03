<?php

declare(strict_types=1);

namespace App\Application\Community\Queries;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Réponses d'une question, triées et paginées.
 */
final class ListAnswers
{
    public const int PER_PAGE = 20;

    /**
     * Ordre : la réponse acceptée d'abord, puis les plus utiles, puis les plus anciennes.
     *
     * @param  User|null  $viewer  Compte connecté, s'il y en a un : sert à dire s'il a déjà voté « Utile ».
     * @return LengthAwarePaginator<int, Answer>
     */
    public function handle(Question $question, ?User $viewer, int $page): LengthAwarePaginator
    {
        $answers = Answer::query()
            ->where('question_id', $question->id)
            ->with('author.profile')
            ->withCount('voters');

        if ($viewer !== null) {
            $answers->withExists([
                'voters as voted_by_viewer' => fn (Builder $voter) => $voter->where('users.id', $viewer->id),
            ]);
        }

        if ($question->accepted_answer_id !== null) {
            $answers->orderByRaw('(answers.id = ?) desc', [$question->accepted_answer_id]);
        }

        $paginator = $answers
            ->orderByDesc('voters_count')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        // Chaque réponse connaît sa question sans nouvelle requête (pour savoir si elle est acceptée).
        $paginator->getCollection()->each(fn (Answer $answer) => $answer->setRelation('question', $question));

        return $paginator;
    }
}
