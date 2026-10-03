<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Application\Community\ReputationLedger;
use App\Domain\Community\Enums\ReputationReason;
use App\Domain\Community\Exceptions\AnswerNotInQuestion;
use App\Models\Answer;
use App\Models\Question;
use App\Notifications\AnswerAccepted;
use Illuminate\Support\Facades\DB;

/**
 * L'auteur d'une question désigne la réponse qui l'a aidé. Une seule réponse
 * peut être acceptée : en choisir une autre remplace la précédente.
 */
final class AcceptAnswer
{
    public function __construct(private readonly ReputationLedger $reputation) {}

    /**
     * @throws AnswerNotInQuestion si la réponse n'existe pas dans cette question (ou a été supprimée).
     */
    public function handle(Question $question, string $answerId): void
    {
        $answer = $question->answers()->whereKey($answerId)->first();

        if ($answer === null) {
            throw new AnswerNotInQuestion;
        }

        // Accepter de nouveau la même réponse ne change rien (ni points, ni notification en double).
        if ($question->accepted_answer_id === $answer->id) {
            return;
        }

        DB::transaction(function () use ($question, $answer): void {
            $this->revokePointsOfAcceptedAnswer($question);

            $question->update(['accepted_answer_id' => $answer->id]);

            $this->rewardAuthor($question, $answer);
        });
    }

    /**
     * Retire la réponse acceptée : la question redevient « non résolue » et les points sont repris.
     */
    public function clear(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            $this->revokePointsOfAcceptedAnswer($question);

            $question->update(['accepted_answer_id' => null]);
        });
    }

    private function revokePointsOfAcceptedAnswer(Question $question): void
    {
        if ($question->accepted_answer_id !== null) {
            $this->reputation->revoke(ReputationReason::AnswerAccepted, $question->accepted_answer_id);
        }
    }

    /**
     * Anti-triche : accepter sa propre réponse est permis, mais ne rapporte ni points ni notification.
     */
    private function rewardAuthor(Question $question, Answer $answer): void
    {
        if ($answer->user_id === $question->user_id) {
            return;
        }

        $this->reputation->award($answer->user_id, ReputationReason::AnswerAccepted, $answer->id, $question->user_id);

        $answer->author->notify(new AnswerAccepted($question, $answer));
    }
}
