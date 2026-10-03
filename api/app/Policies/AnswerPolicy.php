<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Answer;
use App\Models\User;

/**
 * Qui a le droit de faire quoi sur une réponse.
 */
final class AnswerPolicy
{
    /**
     * Seul l'auteur modifie sa réponse.
     */
    public function update(User $user, Answer $answer): bool
    {
        return $answer->user_id === $user->id;
    }

    /**
     * L'auteur supprime sa réponse ; un administrateur peut la retirer (modération).
     */
    public function delete(User $user, Answer $answer): bool
    {
        return $answer->user_id === $user->id || $user->isAdmin();
    }
}
