<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Showcase\Enums\ImportStatus;
use App\Models\Showcase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Showcase>
 */
class ShowcaseFactory extends Factory
{
    /**
     * Par défaut : un projet dont le code a déjà été importé.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Paiement Wave pour Laravel',
            'description' => 'Intégration du paiement **Wave** dans une application Laravel.',
            'repository_owner' => 'awa-diop',
            'repository_name' => 'laravel-wave',
            'branch' => null,
            'demo_url' => null,
            'import_status' => ImportStatus::Done,
            'imported_at' => now(),
        ];
    }

    /**
     * Projet tout juste créé, dont l'import n'a pas commencé.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'import_status' => ImportStatus::Pending,
            'imported_at' => null,
        ]);
    }

    /**
     * Projet dont l'import a échoué.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'import_status' => ImportStatus::Failed,
            'import_error' => 'depot_introuvable',
            'imported_at' => null,
        ]);
    }
}
