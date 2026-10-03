<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Shared\Enums\ActorType;
use App\Models\Order;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes) => Project::query()->whereKey($attributes['project_id'])->valueOrFail('user_id'),
            'status' => OrderStatus::PendingPayment,
            'actor' => ActorType::Human,
            'amount_fcfa' => 5000,
            'quote' => ['lignes' => [['libelle' => 'Service web — petite', 'prix_fcfa' => 5000]], 'total_fcfa' => 5000],
            'payment_url' => 'https://paiement.exemple/lien',
            'payment_expires_at' => now()->addMinutes(15),
            'capacity_reserved_until' => now()->addMinutes(15),
        ];
    }

    /**
     * Commande payée : la période de 30 jours a commencé.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'provider' => 'simulation',
            'provider_reference' => 'sim_'.fake()->unique()->uuid(),
            'paid_at' => now(),
            'period_starts_at' => now(),
            'period_ends_at' => now()->addDays(30),
        ]);
    }

    /**
     * Lien de paiement expiré sans paiement.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Expired,
            'payment_expires_at' => now()->subMinute(),
            'capacity_reserved_until' => now()->subMinute(),
        ]);
    }
}
