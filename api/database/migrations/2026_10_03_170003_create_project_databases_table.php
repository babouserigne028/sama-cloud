<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bases de données d'un projet. Elles ne sont jamais exposées sur internet.
     */
    public function up(): void
    {
        Schema::create('project_databases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();

            $table->string('name', 30);
            $table->string('engine', 20);
            $table->string('version', 10);
            $table->string('size', 10);

            // Identifiants de connexion, chiffrés par l'application avant écriture.
            $table->text('credentials')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_databases');
    }
};
