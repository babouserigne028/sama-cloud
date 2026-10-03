<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Shared\Enums\ActorType;
use Carbon\CarbonImmutable;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne du journal d'audit : qui a fait quoi, humain ou IA.
 *
 * Le journal est en « ajout seul ». PostgreSQL refuse toute modification ou
 * suppression grâce à un déclencheur (voir la migration) : même un bogue ou
 * un accès direct à la base ne peut pas réécrire l'historique.
 *
 * @property string $id
 * @property int|null $user_id
 * @property ActorType $actor
 * @property int|null $token_id
 * @property string $action
 * @property string|null $project_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'user_id', 'actor', 'token_id', 'action',
    'project_id', 'subject_type', 'subject_id',
    'metadata', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUlids;

    /**
     * Pas de colonne « updated_at » : une ligne du journal n'est jamais modifiée.
     */
    public const ?string UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor' => ActorType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
