<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Domain\Community\Exceptions\CannotVoteForOwnAnswer;
use App\Models\Answer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Vote « Utile » sur une réponse : un seul par compte et par réponse.
 */
final class MarkAnswerUseful
{
    /**
     * Voter deux fois ne change rien : le second vote est ignoré, sans erreur.
     *
     * @throws CannotVoteForOwnAnswer si le compte vote pour sa propre réponse.
     */
    public function handle(Answer $answer, User $voter): void
    {
        if ($answer->user_id === $voter->id) {
            throw new CannotVoteForOwnAnswer;
        }

        // « insertOrIgnore » : la base refuse le doublon grâce à la clé primaire, sans lever d'erreur.
        DB::table('answer_votes')->insertOrIgnore([
            'answer_id' => $answer->id,
            'user_id' => $voter->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Retirer un vote qui n'existe pas ne change rien.
     */
    public function remove(Answer $answer, User $voter): void
    {
        $answer->voters()->detach($voter->id);
    }
}
