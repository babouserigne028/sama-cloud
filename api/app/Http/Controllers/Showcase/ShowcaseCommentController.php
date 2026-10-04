<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Application\Showcase\Actions\CommentShowcase;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShowcaseCommentResource;
use App\Models\Showcase;
use App\Models\ShowcaseComment;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Communauté — vitrine de projets', weight: 8)]
final class ShowcaseCommentController extends Controller
{
    /**
     * Lister les commentaires d'un projet.
     *
     * Lecture publique, du plus récent au plus ancien, 20 par page.
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function index(string $id): AnonymousResourceCollection
    {
        $comments = Showcase::query()->findOrFail($id)
            ->comments()
            ->with('author.profile')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return ShowcaseCommentResource::collection($comments);
    }

    /**
     * Commenter un projet.
     *
     * Le propriétaire du projet reçoit une notification.
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function store(Request $request, #[CurrentUser] User $user, string $id, CommentShowcase $commentShowcase): JsonResponse
    {
        $data = $request->validate([
            // Texte simple, 2 à 2000 caractères.
            'contenu' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $comment = $commentShowcase->handle(
            showcase: Showcase::query()->with('owner')->findOrFail($id),
            author: $user,
            body: (string) $data['contenu'],
        );

        return (new ShowcaseCommentResource($comment->load('author.profile')))->response()->setStatusCode(201);
    }

    /**
     * Supprimer un commentaire.
     *
     * Réservé à son auteur, au propriétaire du projet commenté, ou à un administrateur.
     *
     * @param  string  $id  Identifiant du commentaire.
     */
    public function destroy(string $id): Response
    {
        // Un commentaire dont le projet a été retiré est « introuvable ».
        $comment = ShowcaseComment::query()->whereHas('showcase')->with('showcase')->findOrFail($id);

        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
