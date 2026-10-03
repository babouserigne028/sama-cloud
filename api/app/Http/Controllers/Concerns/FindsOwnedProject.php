<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\User;

/**
 * Retrouve un projet par son nom, uniquement parmi ceux du compte connecté.
 */
trait FindsOwnedProject
{
    /**
     * Le projet d'un autre compte est « introuvable » (404) et non « interdit » (403) :
     * on ne révèle pas quels noms de projets existent chez les autres.
     */
    private function findOwnedProject(User $user, string $name): Project
    {
        return $user->projects()->where('name', $name)->firstOrFail();
    }
}
