<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Showcase;
use App\Models\User;

/**
 * Qui a le droit de faire quoi sur un projet de la vitrine.
 */
final class ShowcasePolicy
{
    /**
     * Seul le propriétaire modifie la présentation ou relance l'import.
     */
    public function update(User $user, Showcase $showcase): bool
    {
        return $showcase->user_id === $user->id;
    }

    /**
     * Le propriétaire retire son projet ; un administrateur peut le retirer (modération).
     */
    public function delete(User $user, Showcase $showcase): bool
    {
        return $showcase->user_id === $user->id || $user->isAdmin();
    }
}
