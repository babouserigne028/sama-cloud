<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Community\Enums\ReputationReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ligne de réputation : des points gagnés par un développeur, pour une raison précise.
 *
 * @property int $id
 * @property int $user_id
 * @property ReputationReason $reason
 * @property int $points
 * @property string $subject_type
 * @property string $subject_id
 * @property int|null $source_user_id
 */
#[Fillable(['user_id', 'reason', 'points', 'subject_type', 'subject_id', 'source_user_id'])]
class ReputationEvent extends Model
{
    public const ?string UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReputationReason::class,
            'points' => 'integer',
        ];
    }
}
