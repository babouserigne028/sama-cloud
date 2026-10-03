<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Project\Enums\VariableOrigin;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvironmentVariable>
 */
class EnvironmentVariableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'service_name' => null,
            'name' => 'APP_ENV',
            'value' => 'production',
            'origin' => VariableOrigin::Plain,
        ];
    }

    /**
     * Secret fourni par le client.
     */
    public function secret(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'MAIL_PASSWORD',
            'value' => 'valeur-tres-secrete',
            'origin' => VariableOrigin::Secret,
        ]);
    }

    /**
     * Secret déclaré dans datacloud.yaml mais pas encore fourni.
     */
    public function awaitingValue(): static
    {
        return $this->secret()->state(fn (array $attributes) => [
            'value' => null,
        ]);
    }
}
