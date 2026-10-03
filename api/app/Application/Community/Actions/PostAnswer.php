<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Notifications\AnswerPosted;

/**
 * Publie une réponse à une question et prévient l'auteur de la question.
 */
final class PostAnswer
{
    public function handle(Question $question, User $author, string $body): Answer
    {
        $answer = $question->answers()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        // On ne se prévient pas soi-même quand on répond à sa propre question.
        if ($question->user_id !== $author->id) {
            $author->loadMissing('profile');
            $question->author->notify(new AnswerPosted($question, $answer, $author));
        }

        return $answer;
    }
}
