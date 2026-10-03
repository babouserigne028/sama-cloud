<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Project\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Nom au format de datacloud.yaml : minuscules, chiffres et tirets.
        $name = 'projet-'.Str::lower(Str::random(8));

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => $name,
            'repository_url' => 'https://github.com/exemple/'.$name.'.git',
            'branch' => 'main',
            'status' => ProjectStatus::Active,
            'config' => ['version' => 1, 'services' => ['web' => ['framework' => 'laravel']]],
            'paid_until' => now()->addDays(30),
        ];
    }

    /**
     * Projet créé mais pas encore payé.
     */
    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::PendingPayment,
            'paid_until' => null,
        ]);
    }

    /**
     * Projet arrêté à la fin de la période payée.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Stopped,
            'paid_until' => now()->subDay(),
        ]);
    }
}
