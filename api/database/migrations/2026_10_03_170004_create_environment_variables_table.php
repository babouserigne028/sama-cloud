<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variables d'environnement d'un projet. Les valeurs sont toujours chiffrées.
     */
    public function up(): void
    {
        Schema::create('environment_variables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();

            // Nom du service concerné. Vide = variable partagée par tous les services du projet.
            $table->string('service_name', 30)->nullable();
            $table->string('name', 64);

            // Valeur chiffrée. Vide = secret déclaré mais pas encore fourni par le client.
            $table->text('value')->nullable();
            $table->string('origin', 10);

            $table->timestamps();

            // Une seule valeur par (projet, service, nom). « nullsNotDistinct » : deux lignes
            // sans service et de même nom sont bien vues comme un doublon par PostgreSQL.
            $table->unique(['project_id', 'service_name', 'name'])->nullsNotDistinct();
        });

        // Même règle que datacloud.schema.json : majuscules, chiffres et « _ ».
        DB::statement("ALTER TABLE environment_variables ADD CONSTRAINT environment_variables_name_format CHECK (name ~ '^[A-Z][A-Z0-9_]{0,63}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_variables');
    }
};
