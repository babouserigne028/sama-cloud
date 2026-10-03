<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\AskQuestion;
use App\Application\Community\Actions\DeleteQuestion;
use App\Application\Community\Actions\UpdateQuestion;
use App\Application\Community\Queries\GetQuestionDetails;
use App\Application\Community\Queries\SearchQuestions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\SearchQuestionsRequest;
use App\Http\Requests\Community\StoreQuestionRequest;
use App\Http\Requests\Community\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuestionSummaryResource;
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
final class QuestionController extends Controller
{
    /**
     * Lister les questions.
     *
     * Recherche publique par texte, technologie et statut, de la plus récente à la plus ancienne.
     *
     * @unauthenticated
     */
    public function index(SearchQuestionsRequest $request, SearchQuestions $searchQuestions): AnonymousResourceCollection
    {
        $questions = $searchQuestions->handle(
            search: $request->filled('q') ? $request->string('q')->trim()->toString() : null,
            technologySlug: $request->filled('technologie') ? $request->string('technologie')->toString() : null,
            resolved: $request->resolved(),
            perPage: $request->integer('par_page', 20),
        );

        return QuestionSummaryResource::collection($questions);
    }

    /**
     * Voir une question et ses réponses.
     *
     * Lecture publique. Les 20 premières réponses sont jointes ; les suivantes se lisent avec
     * GET /api/questions/{id}/reponses. Avec un jeton, « vote_par_moi » indique les réponses
     * pour lesquelles le compte a déjà voté « Utile ».
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function show(Request $request, string $id, GetQuestionDetails $getQuestionDetails): QuestionResource
    {
        $viewer = $request->user('sanctum');

        return new QuestionResource($getQuestionDetails->handle($id, $viewer instanceof User ? $viewer : null));
    }

    /**
     * Poser une question.
     */
    public function store(StoreQuestionRequest $request, #[CurrentUser] User $user, AskQuestion $askQuestion, GetQuestionDetails $getQuestionDetails): JsonResponse
    {
        $question = $askQuestion->handle(
            author: $user,
            title: $request->string('titre')->trim()->toString(),
            body: $request->string('contenu')->toString(),
            technologySlugs: array_values($request->array('technologies')),
        );

        return (new QuestionResource($getQuestionDetails->handle($question->id, $user)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Modifier sa question.
     *
     * Réservé à l'auteur. Seuls les champs envoyés sont modifiés.
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function update(UpdateQuestionRequest $request, #[CurrentUser] User $user, string $id, UpdateQuestion $updateQuestion, GetQuestionDetails $getQuestionDetails): QuestionResource
    {
        $question = Question::query()->findOrFail($id);

        Gate::authorize('update', $question);

        $updateQuestion->handle($question, $request->changes());

        return new QuestionResource($getQuestionDetails->handle($question->id, $user));
    }

    /**
     * Supprimer sa question.
     *
     * Réservé à l'auteur, ou à un administrateur pour la modération. La question et ses réponses
     * ne sont plus visibles.
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function destroy(string $id, DeleteQuestion $deleteQuestion): Response
    {
        $question = Question::query()->findOrFail($id);

        Gate::authorize('delete', $question);

        $deleteQuestion->handle($question);

        return response()->noContent();
    }
}
