<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Project\Enums\ProjectStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Projet d'un développeur : il regroupe des services, des bases et des variables.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $repository_url
 * @property string|null $branch
 * @property ProjectStatus $status
 * @property array<string, mixed>|null $config
 * @property CarbonImmutable|null $paid_until
 */
#[Fillable(['user_id', 'name', 'slug', 'repository_url', 'branch', 'status', 'config', 'paid_until'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'config' => 'array',
            'paid_until' => 'datetime',
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
     * @return HasMany<ProjectService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(ProjectService::class);
    }

    /**
     * @return HasMany<ProjectDatabase, $this>
     */
    public function databases(): HasMany
    {
        return $this->hasMany(ProjectDatabase::class);
    }

    /**
     * @return HasMany<EnvironmentVariable, $this>
     */
    public function environmentVariables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<Operation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
