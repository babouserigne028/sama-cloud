<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Community\Enums\ReputationReason;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'auteur d'une réponse qu'elle a été acceptée.
 */
final class AnswerAccepted extends Notification
{
    public const string CODE = 'reponse_acceptee';

    public function __construct(
        private readonly Question $question,
        private readonly Answer $answer,
    ) {}

    /**
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
            'points' => ReputationReason::AnswerAccepted->points(),
        ];
    }
}
