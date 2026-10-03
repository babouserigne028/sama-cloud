<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\DeleteAnswer;
use App\Application\Community\Actions\PostAnswer;
use App\Application\Community\Queries\ListAnswers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\AnswerRequest;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Communauté — questions-réponses', weight: 6)]
final class AnswerController extends Controller
{
    /**
     * Lister les réponses d'une question.
     *
     * Lecture publique, 20 réponses par page : la réponse acceptée d'abord, puis les plus utiles,
     * puis les plus anciennes. La première page est déjà jointe à GET /api/questions/{id}.
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function index(Request $request, string $id, ListAnswers $listAnswers): AnonymousResourceCollection
    {
        $filters = $request->validate([
            // Numéro de page (1 par défaut).
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $viewer = $request->user('sanctum');

        $answers = $listAnswers->handle(
            question: Question::query()->findOrFail($id),
            viewer: $viewer instanceof User ? $viewer : null,
            page: (int) ($filters['page'] ?? 1),
        );

        return AnswerResource::collection($answers);
    }

    /**
     * Répondre à une question.
     *
     * L'auteur de la question reçoit une notification.
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function store(AnswerRequest $request, #[CurrentUser] User $user, string $id, PostAnswer $postAnswer): JsonResponse
    {
        $answer = $postAnswer->handle(
            question: Question::query()->with('author')->findOrFail($id),
            author: $user,
            body: $request->string('contenu')->toString(),
        );

        return (new AnswerResource($this->prepare($answer)))->response()->setStatusCode(201);
    }

    /**
     * Modifier sa réponse.
     *
     * Réservé à l'auteur de la réponse.
     *
     * @param  string  $id  Identifiant de la réponse.
     */
    public function update(AnswerRequest $request, string $id): AnswerResource
    {
        $answer = $this->findVisibleAnswer($id);

        Gate::authorize('update', $answer);

        $answer->update(['body' => $request->string('contenu')->toString()]);

        return new AnswerResource($this->prepare($answer));
    }

    /**
     * Supprimer sa réponse.
     *
     * Réservé à l'auteur, ou à un administrateur pour la modération. Si c'était la réponse acceptée,
     * la question redevient « non résolue ». Les points qu'elle avait rapportés sont repris.
     *
     * @param  string  $id  Identifiant de la réponse.
     */
    public function destroy(string $id, DeleteAnswer $deleteAnswer): Response
    {
        $answer = $this->findVisibleAnswer($id);

        Gate::authorize('delete', $answer);

        $deleteAnswer->handle($answer);

        return response()->noContent();
    }

    /**
     * Une réponse dont la question a été supprimée est « introuvable ».
     */
    private function findVisibleAnswer(string $id): Answer
    {
        return Answer::query()->whereHas('question')->findOrFail($id);
    }

    /**
     * Charge ce dont la ressource a besoin pour être renvoyée.
     */
    private function prepare(Answer $answer): Answer
    {
        return $answer->load(['author.profile', 'question'])->loadCount('voters');
    }
}
