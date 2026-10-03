<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Project\Enums\ResourceSize;
use App\Models\Project;
use App\Models\ProjectDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDatabase>
 */
class ProjectDatabaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'principale',
            'engine' => 'postgresql',
            'version' => '16',
            'size' => ResourceSize::Small,
            'credentials' => null,
        ];
    }
}
