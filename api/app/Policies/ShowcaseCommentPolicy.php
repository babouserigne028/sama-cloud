<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShowcaseComment;
use App\Models\User;

/**
 * Qui a le droit de supprimer un commentaire de la vitrine.
 */
final class ShowcaseCommentPolicy
{
    /**
     * L'auteur du commentaire, le propriétaire du projet commenté (il modère sa page),
     * ou un administrateur. La relation « showcase » doit être chargée.
     */
    public function delete(User $user, ShowcaseComment $comment): bool
    {
        return $comment->user_id === $user->id
            || $comment->showcase->user_id === $user->id
            || $user->isAdmin();
    }
}
