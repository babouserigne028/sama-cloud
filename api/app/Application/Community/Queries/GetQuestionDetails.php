<?php

declare(strict_types=1);

namespace App\Application\Community\Queries;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Charge une question avec tout ce qu'il faut pour l'afficher : auteur, technologies et réponses triées.
 */
final class GetQuestionDetails
{
    /**
     * Ordre des réponses : la réponse acceptée d'abord, puis les plus utiles, puis les plus anciennes.
     *
     * @param  User|null  $viewer  Compte connecté, s'il y en a un : sert à dire s'il a déjà voté « Utile ».
     */
    public function handle(string $questionId, ?User $viewer): Question
    {
        $question = Question::query()
            ->with(['author.profile', 'technologies'])
            ->withCount('answers')
            ->findOrFail($questionId);

        $answers = Answer::query()
            ->where('question_id', $question->id)
            ->with('author.profile')
            ->withCount('voters');

        if ($viewer !== null) {
            $answers->withExists([
                'voters as voted_by_viewer' => fn (Builder $voter) => $voter->where('users.id', $viewer->id),
            ]);
        }

        if ($question->accepted_answer_id !== null) {
            $answers->orderByRaw('(answers.id = ?) desc', [$question->accepted_answer_id]);
        }

        $sortedAnswers = $answers
            ->orderByDesc('voters_count')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Chaque réponse connaît sa question sans nouvelle requête (pour savoir si elle est acceptée).
        $sortedAnswers->each(fn (Answer $answer) => $answer->setRelation('question', $question));

        return $question->setRelation('answers', $sortedAnswers);
    }
}
