<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Community\Enums\ReportableType;
use App\Domain\Community\Enums\ReportReason;
use App\Domain\Community\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Signalement d'un contenu par un membre.
 *
 * @property string $id
 * @property int $user_id
 * @property ReportableType $content_type
 * @property string $content_id
 * @property ReportReason $reason
 * @property string|null $details
 * @property ReportStatus $status
 * @property int|null $handled_by
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable $created_at
 * @property-read User $reporter
 */
#[Fillable(['user_id', 'content_type', 'content_id', 'reason', 'details', 'status', 'handled_by', 'handled_at'])]
class Report extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => ReportableType::class,
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Membre qui a fait le signalement.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
