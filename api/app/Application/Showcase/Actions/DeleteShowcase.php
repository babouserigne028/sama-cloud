<?php

declare(strict_types=1);

namespace App\Application\Showcase\Actions;

use App\Application\Community\ReputationLedger;
use App\Models\Showcase;
use Illuminate\Support\Facades\DB;

/**
 * Retire un projet de la vitrine (suppression « douce »). Ses étoiles ne rapportent plus de points.
 */
final class DeleteShowcase
{
    public function __construct(private readonly ReputationLedger $reputation) {}

    public function handle(Showcase $showcase): void
    {
        DB::transaction(function () use ($showcase): void {
            $this->reputation->revokeAllFor(ReputationLedger::SHOWCASE, [$showcase->id]);

            $showcase->delete();
        });
    }
}
