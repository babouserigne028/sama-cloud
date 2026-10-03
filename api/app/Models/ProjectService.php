<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Project\Enums\ResourceSize;
use App\Domain\Project\Enums\ServiceType;
use Database\Factories\ProjectServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service d'un projet : une application web ou une tâche de fond (worker).
 *
 * @property string $id
 * @property string $project_id
 * @property string $name
 * @property ServiceType $type
 * @property string|null $framework
 * @property ResourceSize $size
 * @property string|null $hostname
 */
#[Fillable(['project_id', 'name', 'type', 'framework', 'size', 'hostname'])]
class ProjectService extends Model
{
    /** @use HasFactory<ProjectServiceFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'size' => ResourceSize::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
