<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Deployment\Enums\DeploymentStatus;
use App\Domain\Shared\Enums\ActorType;
use Carbon\CarbonImmutable;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Déploiement d'un projet : une tentative de mise en ligne d'une version.
 *
 * @property string $id
 * @property string $project_id
 * @property string $operation_id
 * @property int $user_id
 * @property ActorType $actor
 * @property DeploymentStatus $status
 * @property string|null $branch
 * @property string|null $commit_sha
 * @property array<string, mixed> $config
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
#[Fillable(['project_id', 'operation_id', 'user_id', 'actor', 'status', 'branch', 'commit_sha', 'config', 'failure_reason', 'started_at', 'finished_at'])]
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor' => ActorType::class,
            'status' => DeploymentStatus::class,
            'config' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Operation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
