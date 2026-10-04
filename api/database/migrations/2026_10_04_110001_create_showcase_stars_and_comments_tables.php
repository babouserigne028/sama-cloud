<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Étoiles et commentaires des projets de la vitrine.
     */
    public function up(): void
    {
        // La clé primaire garantit une seule étoile par compte et par projet.
        Schema::create('showcase_stars', function (Blueprint $table) {
            $table->foreignUlid('showcase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->primary(['showcase_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('showcase_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('showcase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Texte simple (pas de Markdown) : court avis ou question sur le projet.
            $table->text('body');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['showcase_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_comments');
        Schema::dropIfExists('showcase_stars');
    }
};
