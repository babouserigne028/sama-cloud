<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

/**
 * Qui a le droit de faire quoi sur une question.
 */
final class QuestionPolicy
{
    /**
     * Seul l'auteur modifie sa question.
     */
    public function update(User $user, Question $question): bool
    {
        return $question->user_id === $user->id;
    }

    /**
     * L'auteur supprime sa question ; un administrateur peut la retirer (modération).
     */
    public function delete(User $user, Question $question): bool
    {
        return $question->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Seul l'auteur de la question choisit (ou retire) la réponse acceptée.
     */
    public function acceptAnswer(User $user, Question $question): bool
    {
        return $question->user_id === $user->id;
    }
}
