<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Deployment\Enums\OperationStatus;
use App\Domain\Deployment\Enums\OperationType;
use Carbon\CarbonImmutable;
use Database\Factories\OperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Opération longue (build, création ou suppression de base…).
 * Le client reçoit son identifiant tout de suite, puis suit l'avancement.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $project_id
 * @property OperationType $type
 * @property OperationStatus $status
 * @property string|null $step
 * @property int $progress
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
#[Fillable(['user_id', 'project_id', 'type', 'status', 'step', 'progress', 'error_code', 'error_message', 'started_at', 'finished_at'])]
class Operation extends Model
{
    /** @use HasFactory<OperationFactory> */
    use HasFactory, HasUlids;

    /**
     * Valeurs par défaut d'une nouvelle opération (identiques à celles de la base).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'progress' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'status' => OperationStatus::class,
            'progress' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Le déploiement suivi par cette opération, s'il s'agit d'un déploiement.
     *
     * @return HasOne<Deployment, $this>
     */
    public function deployment(): HasOne
    {
        return $this->hasOne(Deployment::class);
    }
}
