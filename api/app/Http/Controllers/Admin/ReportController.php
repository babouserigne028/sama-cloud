<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Community\Actions\ResolveReport;
use App\Domain\Community\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

#[Group('Administration', weight: 9)]
final class ReportController extends Controller
{
    /**
     * Lister les signalements.
     *
     * Réservé aux administrateurs. Par défaut, les signalements ouverts, du plus ancien au plus récent.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            // « ouvert » (par défaut), « retenu » ou « rejete ».
            'statut' => ['nullable', Rule::enum(ReportStatus::class)],
        ]);

        $reports = Report::query()
            ->with('reporter.profile')
            ->where('status', $filters['statut'] ?? ReportStatus::Open->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(20);

        return ReportResource::collection($reports);
    }

    /**
     * Décider d'un signalement.
     *
     * Réservé aux administrateurs. « retirer » supprime le contenu et règle tous les signalements
     * ouverts sur ce contenu ; « rejeter » laisse le contenu en ligne.
     *
     * @param  string  $id  Identifiant du signalement.
     */
    #[ApiError(409, 'signalement_deja_traite', 'Le signalement a déjà reçu une décision.')]
    public function resolve(ResolveReportRequest $request, #[CurrentUser] User $admin, string $id, ResolveReport $resolveReport): ReportResource
    {
        $report = $resolveReport->handle(
            report: Report::query()->findOrFail($id),
            admin: $admin,
            removeContent: $request->string('decision')->toString() === 'retirer',
        );

        return new ReportResource($report->load('reporter.profile'));
    }
}
