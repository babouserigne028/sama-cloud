<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Project\Enums\VariableOrigin;
use Database\Factories\EnvironmentVariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variable d'environnement d'un projet.
 *
 * La valeur est chiffrée en base et masquée dans toute conversion en tableau
 * ou en JSON : l'API ne renvoie que les noms des variables, jamais les valeurs.
 *
 * @property string $id
 * @property string $project_id
 * @property string|null $service_name
 * @property string $name
 * @property string|null $value
 * @property VariableOrigin $origin
 */
#[Fillable(['project_id', 'service_name', 'name', 'value', 'origin'])]
#[Hidden(['value'])]
class EnvironmentVariable extends Model
{
    /** @use HasFactory<EnvironmentVariableFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'origin' => VariableOrigin::class,
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
     * Un secret déclaré mais pas encore fourni bloque le déploiement.
     */
    public function isMissingValue(): bool
    {
        return $this->value === null;
    }
}
