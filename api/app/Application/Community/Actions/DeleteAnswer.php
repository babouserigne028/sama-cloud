<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Application\Community\ReputationLedger;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une réponse (suppression « douce » : elle est masquée, pas effacée).
 */
final class DeleteAnswer
{
    public function __construct(private readonly ReputationLedger $reputation) {}

    public function handle(Answer $answer): void
    {
        DB::transaction(function () use ($answer): void {
            // Si c'était la réponse acceptée, la question redevient « non résolue ».
            Question::query()
                ->whereKey($answer->question_id)
                ->where('accepted_answer_id', $answer->id)
                ->update(['accepted_answer_id' => null]);

            // Une réponse retirée ne rapporte plus de points à son auteur.
            $this->reputation->revokeAllForAnswers([$answer->id]);

            $answer->delete();
        });
    }
}
