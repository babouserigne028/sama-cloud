<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Application\Community\ReputationLedger;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une question (suppression « douce »). Ses réponses ne sont plus visibles
 * et ne rapportent plus de points.
 */
final class DeleteQuestion
{
    public function __construct(private readonly ReputationLedger $reputation) {}

    public function handle(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            /** @var list<string> $answerIds */
            $answerIds = $question->answers()->pluck('id')->all();

            $this->reputation->revokeAllForAnswers($answerIds);

            $question->delete();
        });
    }
}
