<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Deployment\Enums\OperationStatus;
use App\Domain\Deployment\Enums\OperationType;
use App\Models\Operation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operation>
 */
class OperationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // Par défaut, l'opération appartient au propriétaire du projet.
            'user_id' => fn (array $attributes) => Project::query()->whereKey($attributes['project_id'])->valueOrFail('user_id'),
            'type' => OperationType::Deployment,
            'status' => OperationStatus::Pending,
            'step' => null,
            'progress' => 0,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OperationStatus::Succeeded,
            'progress' => 100,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OperationStatus::Failed,
            'error_code' => 'build_echoue',
            'error_message' => 'La construction de l\'image a échoué.',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }
}
