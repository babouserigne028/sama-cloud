<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Deployment\Enums\DeploymentStatus;
use App\Domain\Shared\Enums\ActorType;
use App\Models\Deployment;
use App\Models\Operation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // L'opération de suivi et l'auteur sont rattachés au même projet.
            'operation_id' => fn (array $attributes) => Operation::factory()->create(['project_id' => $attributes['project_id']])->id,
            'user_id' => fn (array $attributes) => Project::query()->whereKey($attributes['project_id'])->valueOrFail('user_id'),
            'actor' => ActorType::Human,
            'status' => DeploymentStatus::Queued,
            'branch' => 'main',
            'commit_sha' => fake()->sha1(),
            'config' => ['version' => 1, 'services' => ['web' => ['framework' => 'laravel']]],
        ];
    }

    /**
     * Déploiement demandé par l'IA (jeton « agent IA »).
     */
    public function byAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor' => ActorType::Ai,
        ]);
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeploymentStatus::Live,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeploymentStatus::Failed,
            'failure_reason' => 'La commande de build a échoué.',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now(),
        ]);
    }
}
