<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'auteur d'une question qu'elle vient de recevoir une réponse.
 */
final class AnswerPosted extends Notification
{
    public const string CODE = 'reponse_recue';

    public function __construct(
        private readonly Question $question,
        private readonly Answer $answer,
        private readonly User $answerAuthor,
    ) {}

    /**
     * Enregistrée en base : le back-office l'affiche dans la liste des notifications.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => self::CODE,
            'question_id' => $this->question->id,
            'question_titre' => $this->question->title,
            'reponse_id' => $this->answer->id,
            'auteur' => [
                'pseudo' => $this->answerAuthor->profile?->username,
                'nom' => $this->answerAuthor->name,
            ],
        ];
    }
}
