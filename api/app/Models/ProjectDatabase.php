<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Project\Enums\ResourceSize;
use Database\Factories\ProjectDatabaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Base de données d'un projet.
 *
 * Les identifiants de connexion sont chiffrés en base et masqués dans
 * toute conversion en tableau ou en JSON : ils ne sortent jamais de l'API.
 *
 * @property string $id
 * @property string $project_id
 * @property string $name
 * @property string $engine
 * @property string $version
 * @property ResourceSize $size
 * @property array<string, mixed>|null $credentials
 */
#[Fillable(['project_id', 'name', 'engine', 'version', 'size', 'credentials'])]
#[Hidden(['credentials'])]
class ProjectDatabase extends Model
{
    /** @use HasFactory<ProjectDatabaseFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => ResourceSize::class,
            'credentials' => 'encrypted:array',
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
