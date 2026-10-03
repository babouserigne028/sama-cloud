<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Domain\Community\Exceptions\AnswerNotInQuestion;
use App\Models\Question;

/**
 * L'auteur d'une question désigne la réponse qui l'a aidé. Une seule réponse
 * peut être acceptée : en choisir une autre remplace la précédente.
 */
final class AcceptAnswer
{
    /**
     * @throws AnswerNotInQuestion si la réponse n'existe pas dans cette question (ou a été supprimée).
     */
    public function handle(Question $question, string $answerId): void
    {
        $belongsToQuestion = $question->answers()->whereKey($answerId)->exists();

        if (! $belongsToQuestion) {
            throw new AnswerNotInQuestion;
        }

        $question->update(['accepted_answer_id' => $answerId]);
    }
}
