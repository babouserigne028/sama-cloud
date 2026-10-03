<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signalements : un membre prévient les administrateurs qu'un contenu pose problème.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Membre qui signale.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Contenu signalé : « question » ou « reponse », et son identifiant.
            $table->string('content_type', 20);
            $table->ulid('content_id');

            $table->string('reason', 30);
            $table->string('details', 500)->nullable();

            // « ouvert », puis « retenu » (contenu retiré) ou « rejete ».
            $table->string('status', 20);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            // Un seul signalement par membre et par contenu.
            $table->unique(['user_id', 'content_type', 'content_id']);
            $table->index(['status', 'created_at']);
            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
