<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence nécessaires au fonctionnement de la plateforme.
     * Les données de démonstration (comptes, projets) ont leur propre seeder.
     */
    public function run(): void
    {
        $this->call(TechnologySeeder::class);
    }
}
