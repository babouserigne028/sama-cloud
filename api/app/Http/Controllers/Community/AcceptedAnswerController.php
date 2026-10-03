<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\AcceptAnswer;
use App\Application\Community\Queries\GetQuestionDetails;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Community\AcceptAnswerRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

#[Group('Communauté — questions-réponses', weight: 6)]
final class AcceptedAnswerController extends Controller
{
    /**
     * Accepter une réponse.
     *
     * Réservé à l'auteur de la question. Une seule réponse acceptée par question :
     * en choisir une autre remplace la précédente.
     *
     * @param  string  $id  Identifiant de la question.
     */
    #[ApiError(422, 'reponse_hors_question', "La réponse n'appartient pas à cette question, ou a été supprimée.")]
    public function update(AcceptAnswerRequest $request, #[CurrentUser] User $user, string $id, AcceptAnswer $acceptAnswer, GetQuestionDetails $getQuestionDetails): QuestionResource
    {
        $question = Question::query()->findOrFail($id);

        Gate::authorize('acceptAnswer', $question);

        $acceptAnswer->handle($question, $request->string('reponse_id')->toString());

        return new QuestionResource($getQuestionDetails->handle($question->id, $user));
    }

    /**
     * Retirer la réponse acceptée.
     *
     * Réservé à l'auteur de la question, qui redevient « non résolue ».
     *
     * @param  string  $id  Identifiant de la question.
     */
    public function destroy(#[CurrentUser] User $user, string $id, GetQuestionDetails $getQuestionDetails): QuestionResource
    {
        $question = Question::query()->findOrFail($id);

        Gate::authorize('acceptAnswer', $question);

        $question->update(['accepted_answer_id' => null]);

        return new QuestionResource($getQuestionDetails->handle($question->id, $user));
    }
}
