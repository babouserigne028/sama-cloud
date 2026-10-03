<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\DeleteAnswer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\AnswerRequest;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Communauté — questions-réponses', weight: 6)]
final class AnswerController extends Controller
{
    /**
     * Répondre à une question.
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function store(AnswerRequest $request, #[CurrentUser] User $user, string $id): JsonResponse
    {
        $question = Question::query()->findOrFail($id);

        $answer = $question->answers()->create([
            'user_id' => $user->id,
            'body' => $request->string('contenu')->toString(),
        ]);

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
     * la question redevient « non résolue ».
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
