<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Shared\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor' => ActorType::Human,
            'action' => 'projet.cree',
            'metadata' => ['nom' => 'mon-blog'],
            'ip_address' => '127.0.0.1',
        ];
    }

    /**
     * Action faite par l'IA avec un jeton « agent IA ».
     */
    public function byAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor' => ActorType::Ai,
        ]);
    }
}
