<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Shared\Enums\ActorType;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commande : un devis validé, à payer avant la création des ressources.
 * Les montants sont des entiers en FCFA.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $project_id
 * @property OrderStatus $status
 * @property ActorType $actor
 * @property int $amount_fcfa
 * @property array<string, mixed> $quote
 * @property string|null $payment_url
 * @property CarbonImmutable|null $payment_expires_at
 * @property CarbonImmutable|null $capacity_reserved_until
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $period_starts_at
 * @property CarbonImmutable|null $period_ends_at
 */
#[Fillable([
    'user_id', 'project_id', 'status', 'actor', 'amount_fcfa', 'quote',
    'payment_url', 'payment_expires_at', 'capacity_reserved_until',
    'provider', 'provider_reference', 'paid_at',
    'period_starts_at', 'period_ends_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'actor' => ActorType::class,
            'amount_fcfa' => 'integer',
            'quote' => 'array',
            'payment_expires_at' => 'datetime',
            'capacity_reserved_until' => 'datetime',
            'paid_at' => 'datetime',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
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
}
