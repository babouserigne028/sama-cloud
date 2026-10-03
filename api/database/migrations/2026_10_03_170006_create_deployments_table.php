<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des déploiements d'un projet.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            // Un déploiement est suivi par exactement une opération longue.
            $table->foreignUlid('operation_id')->unique()->constrained()->restrictOnDelete();
            // Compte qui a demandé le déploiement.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Demandé par un humain, par l'IA ou par la plateforme.
            $table->string('actor', 10);
            $table->string('status', 20);

            $table->string('branch')->nullable();
            $table->string('commit_sha', 40)->nullable();

            // Copie de la configuration réellement utilisée : on sait toujours
            // avec quoi cette version a été construite.
            $table->jsonb('config');

            $table->text('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
