<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Project\Enums\ResourceSize;
use App\Domain\Project\Enums\ServiceType;
use App\Models\Project;
use App\Models\ProjectService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectService>
 */
class ProjectServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'web',
            'type' => ServiceType::Web,
            'framework' => 'laravel',
            'size' => ResourceSize::Small,
            'hostname' => null,
        ];
    }

    /**
     * Tâche de fond : pas d'adresse publique.
     */
    public function worker(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'file-attente',
            'type' => ServiceType::Worker,
            'hostname' => null,
        ]);
    }
}
