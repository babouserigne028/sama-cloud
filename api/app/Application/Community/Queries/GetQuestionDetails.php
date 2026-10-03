<?php

declare(strict_types=1);

namespace App\Application\Community\Queries;

use App\Models\Question;
use App\Models\User;

/**
 * Charge une question avec tout ce qu'il faut pour l'afficher : auteur, technologies
 * et première page de réponses.
 */
final class GetQuestionDetails
{
    public function __construct(private readonly ListAnswers $listAnswers) {}

    /**
     * Seules les 20 premières réponses sont jointes ; les suivantes se lisent page par page
     * avec ListAnswers (GET /api/questions/{id}/reponses).
     *
     * @param  User|null  $viewer  Compte connecté, s'il y en a un.
     */
    public function handle(string $questionId, ?User $viewer): Question
    {
        $question = Question::query()
            ->with(['author.profile', 'technologies'])
            ->withCount('answers')
            ->findOrFail($questionId);

        $firstPage = $this->listAnswers->handle($question, $viewer, page: 1);

        return $question->setRelation('answers', $firstPage->getCollection());
    }
}
