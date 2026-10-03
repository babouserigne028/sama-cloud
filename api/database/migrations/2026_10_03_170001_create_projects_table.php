<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projets des développeurs : un projet regroupe des services et des bases.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            // Identifiant ULID : impossible à deviner, contrairement à 1, 2, 3…
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Nom choisi par le développeur (champ « nom » de datacloud.yaml).
            $table->string('name', 30);
            // Nom unique sur toute la plateforme, utilisé pour le sous-domaine (ex. mon-blog-4f2a).
            $table->string('slug', 40);

            $table->text('repository_url')->nullable();
            $table->string('branch')->nullable();
            $table->string('status', 30);

            // Dernière configuration datacloud.yaml enregistrée pour ce projet.
            $table->jsonb('config')->nullable();

            // Fin de la période payée (échéance).
            $table->timestamp('paid_until')->nullable();

            $table->timestamps();
            // Suppression « douce » : le projet est masqué mais reste en base.
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // Unicité seulement parmi les projets non supprimés : un nom redevient libre après suppression.
        DB::statement('CREATE UNIQUE INDEX projects_user_id_name_unique ON projects (user_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX projects_slug_unique ON projects (slug) WHERE deleted_at IS NULL');

        // Même règle que datacloud.schema.json : minuscules, chiffres, tirets, commence par une lettre.
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_name_format CHECK (name ~ '^[a-z][a-z0-9-]{1,28}[a-z0-9]$')");
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_slug_format CHECK (slug ~ '^[a-z][a-z0-9-]{1,38}[a-z0-9]$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
