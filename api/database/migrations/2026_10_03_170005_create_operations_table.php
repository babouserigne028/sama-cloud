<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opérations longues (build, création ou suppression de base…).
     * L'API répond tout de suite avec un identifiant ; le client suit l'avancement ensuite.
     */
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Le suivi reste consultable même si le projet est effacé.
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 30);
            $table->string('status', 20);

            // Étape en cours, lisible par un humain (ex. « Construction de l'image »).
            $table->string('step')->nullable();
            // Avancement en pourcentage.
            $table->unsignedSmallInteger('progress')->default(0);

            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement('ALTER TABLE operations ADD CONSTRAINT operations_progress_range CHECK (progress BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
