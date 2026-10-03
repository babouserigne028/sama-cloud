<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\ReportContent;
use App\Domain\Community\Enums\ReportableType;
use App\Domain\Community\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Community\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

#[Group('Communauté — questions-réponses', weight: 6)]
final class ReportController extends Controller
{
    /**
     * Signaler une question.
     *
     * Prévient les administrateurs qu'une question pose problème. Signaler deux fois
     * la même question ne crée pas de doublon.
     *
     * @param  string  $id  Identifiant de la question.
     */
    #[ApiError(403, 'signalement_de_son_contenu', 'On ne signale pas son propre contenu.')]
    public function question(ReportRequest $request, #[CurrentUser] User $user, string $id, ReportContent $reportContent): JsonResponse
    {
        return $this->report($request, $user, ReportableType::Question, $id, $reportContent);
    }

    /**
     * Signaler une réponse.
     *
     * @param  string  $id  Identifiant de la réponse.
     */
    #[ApiError(403, 'signalement_de_son_contenu', 'On ne signale pas son propre contenu.')]
    public function answer(ReportRequest $request, #[CurrentUser] User $user, string $id, ReportContent $reportContent): JsonResponse
    {
        return $this->report($request, $user, ReportableType::Answer, $id, $reportContent);
    }

    private function report(ReportRequest $request, User $user, ReportableType $type, string $id, ReportContent $reportContent): JsonResponse
    {
        $report = $reportContent->handle(
            reporter: $user,
            type: $type,
            contentId: $id,
            reason: ReportReason::from($request->string('motif')->toString()),
            details: $request->filled('details') ? $request->string('details')->toString() : null,
        );

        return (new ReportResource($report))->response()->setStatusCode(201);
    }
}
