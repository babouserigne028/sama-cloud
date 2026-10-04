<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vitrine de projets : un développeur présente un projet à partir de son dépôt GitHub public.
     * SamaCloud garde une copie des fichiers de code, lisible par tous.
     *
     * À ne pas confondre avec la table « projects », qui contient les projets hébergés et payés.
     */
    public function up(): void
    {
        Schema::create('showcases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('title', 100);
            // Présentation du projet, en Markdown.
            $table->text('description');

            // Dépôt GitHub public d'origine. Seuls le compte et le nom servent à l'import.
            $table->string('repository_owner', 39);
            $table->string('repository_name', 100);
            // Branche importée. Vide = la branche par défaut du dépôt.
            $table->string('branch')->nullable();

            // Adresse de la version en ligne (« Voir la démo »).
            $table->string('demo_url')->nullable();

            // Avancement de la copie des fichiers : en_attente, en_cours, termine, echec.
            $table->string('import_status', 20);
            // Code de l'erreur si l'import a échoué (ex. depot_introuvable).
            $table->string('import_error', 40)->nullable();
            $table->timestamp('imported_at')->nullable();

            $table->unsignedInteger('files_count')->default(0);
            $table->unsignedInteger('total_bytes')->default(0);
            // Vrai si des fichiers ont été laissés de côté parce que le dépôt dépasse les limites.
            $table->boolean('is_truncated')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['import_status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('showcase_technology', function (Blueprint $table) {
            $table->foreignUlid('showcase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();

            $table->primary(['showcase_id', 'technology_id']);
            $table->index('technology_id');
        });

        // Copie des fichiers de code. Seuls les fichiers texte sont gardés : pas de binaires, pas de secrets.
        Schema::create('showcase_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('showcase_id')->constrained()->cascadeOnDelete();

            // Chemin dans le dépôt, avec des « / » (ex. app/Models/User.php).
            $table->string('path', 400);
            $table->text('content');
            $table->unsignedInteger('size');

            $table->timestamps();

            $table->unique(['showcase_id', 'path']);
        });

        // Un chemin ne commence jamais par « / » et ne contient jamais « .. » : pas de sortie du dossier du projet.
        DB::statement("ALTER TABLE showcase_files ADD CONSTRAINT showcase_files_path_safe CHECK (path !~ '(^/)|(^|/)\\.\\.(/|$)' AND path <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_files');
        Schema::dropIfExists('showcase_technology');
        Schema::dropIfExists('showcases');
    }
};
