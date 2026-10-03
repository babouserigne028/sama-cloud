<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\MarkAnswerUseful;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Models\Answer;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

#[Group('Communauté — questions-réponses', weight: 6)]
final class AnswerVoteController extends Controller
{
    /**
     * Voter « Utile » pour une réponse.
     *
     * Un seul vote par compte et par réponse : voter deux fois ne change rien.
     * Un jeton d'agent IA ne peut pas voter.
     *
     * @param  string  $id  Identifiant de la réponse.
     */
    #[ApiError(403, 'vote_sur_sa_reponse', 'On ne vote pas pour sa propre réponse.')]
    public function store(#[CurrentUser] User $user, string $id, MarkAnswerUseful $markAnswerUseful): JsonResponse
    {
        $answer = $this->findVisibleAnswer($id);

        $markAnswerUseful->handle($answer, $user);

        return $this->voteState($answer, true);
    }

    /**
     * Retirer son vote « Utile ».
     *
     * @param  string  $id  Identifiant de la réponse.
     */
    public function destroy(#[CurrentUser] User $user, string $id, MarkAnswerUseful $markAnswerUseful): JsonResponse
    {
        $answer = $this->findVisibleAnswer($id);

        $markAnswerUseful->remove($answer, $user);

        return $this->voteState($answer, false);
    }

    private function findVisibleAnswer(string $id): Answer
    {
        return Answer::query()->whereHas('question')->findOrFail($id);
    }

    /**
     * Nouvel état du vote, pour mettre à jour l'affichage sans recharger la question.
     */
    private function voteState(Answer $answer, bool $votedByMe): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'reponse_id' => $answer->id,
                'nb_utile' => $answer->voters()->count(),
                'vote_par_moi' => $votedByMe,
            ],
        ]);
    }
}
