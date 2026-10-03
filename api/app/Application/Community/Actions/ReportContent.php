<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Domain\Community\Enums\ReportableType;
use App\Domain\Community\Enums\ReportReason;
use App\Domain\Community\Enums\ReportStatus;
use App\Domain\Community\Exceptions\CannotReportOwnContent;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Un membre signale une question ou une réponse aux administrateurs.
 */
final class ReportContent
{
    /**
     * Signaler deux fois le même contenu ne crée pas de doublon : le premier signalement est renvoyé.
     *
     * @throws ModelNotFoundException si le contenu n'existe pas ou n'est plus visible.
     * @throws CannotReportOwnContent si le membre signale son propre contenu.
     */
    public function handle(User $reporter, ReportableType $type, string $contentId, ReportReason $reason, ?string $details): Report
    {
        $authorId = $this->visibleContentAuthorId($type, $contentId);

        if ($authorId === $reporter->id) {
            throw new CannotReportOwnContent;
        }

        return Report::query()->firstOrCreate(
            ['user_id' => $reporter->id, 'content_type' => $type, 'content_id' => $contentId],
            ['reason' => $reason, 'details' => $details, 'status' => ReportStatus::Open],
        );
    }

    /**
     * Auteur du contenu signalé. Un contenu supprimé (ou dont la question est supprimée) est introuvable.
     */
    private function visibleContentAuthorId(ReportableType $type, string $contentId): int
    {
        return match ($type) {
            ReportableType::Question => Question::query()->findOrFail($contentId)->user_id,
            ReportableType::Answer => Answer::query()->whereHas('question')->findOrFail($contentId)->user_id,
        };
    }
}
