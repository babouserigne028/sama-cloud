<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsOwnedProject;
use App\Http\Resources\ProjectResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Projets', weight: 3)]
final class ProjectController extends Controller
{
    use FindsOwnedProject;

    /**
     * Lister ses projets.
     *
     * Renvoie tous les projets du compte, du plus récent au plus ancien, avec leur statut,
     * leur adresse, leurs services, leurs bases et leur échéance.
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $projects = $user->projects()
            ->with(['services', 'databases'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * Détail d'un projet.
     *
     * @param  string  $projet  Nom du projet (ex. mon-blog).
     */
    public function show(#[CurrentUser] User $user, string $projet): ProjectResource
    {
        $project = $this->findOwnedProject($user, $projet)->load(['services', 'databases']);

        return new ProjectResource($project);
    }
}
