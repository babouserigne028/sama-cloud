<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Domain\Community\Enums\ReportableType;
use App\Domain\Community\Enums\ReportStatus;
use App\Domain\Community\Exceptions\ReportAlreadyHandled;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Décision d'un administrateur sur un signalement.
 */
final class ResolveReport
{
    public function __construct(
        private readonly DeleteQuestion $deleteQuestion,
        private readonly DeleteAnswer $deleteAnswer,
    ) {}

    /**
     * @param  bool  $removeContent  true = le signalement est justifié, le contenu est retiré ;
     *                               false = le signalement est rejeté, le contenu reste en ligne.
     *
     * @throws ReportAlreadyHandled si le signalement a déjà reçu une décision.
     */
    public function handle(Report $report, User $admin, bool $removeContent): Report
    {
        return DB::transaction(function () use ($report, $admin, $removeContent): Report {
            // Verrou : deux administrateurs ne peuvent pas traiter le même signalement en même temps.
            $report = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($report->status !== ReportStatus::Open) {
                throw new ReportAlreadyHandled;
            }

            $decision = ['handled_by' => $admin->id, 'handled_at' => now()];

            if (! $removeContent) {
                $report->update([...$decision, 'status' => ReportStatus::Dismissed]);

                return $report;
            }

            $this->removeContent($report);

            // Le contenu est retiré : tous les signalements ouverts sur ce contenu sont réglés d'un coup.
            Report::query()
                ->where('content_type', $report->content_type)
                ->where('content_id', $report->content_id)
                ->where('status', ReportStatus::Open)
                ->update([...$decision, 'status' => ReportStatus::Upheld]);

            return $report->refresh();
        });
    }

    /**
     * Retire le contenu signalé, s'il existe encore (son auteur a pu le supprimer entre-temps).
     */
    private function removeContent(Report $report): void
    {
        if ($report->content_type === ReportableType::Question) {
            $question = Question::query()->find($report->content_id);

            if ($question !== null) {
                $this->deleteQuestion->handle($question);
            }

            return;
        }

        $answer = Answer::query()->find($report->content_id);

        if ($answer !== null) {
            $this->deleteAnswer->handle($answer);
        }
    }
}
