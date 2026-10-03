<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Points de réputation : une ligne par raison de gagner des points.
     * Le total d'un développeur est la somme de ses lignes, donc toujours vérifiable.
     */
    public function up(): void
    {
        Schema::create('reputation_events', function (Blueprint $table) {
            $table->id();
            // Développeur qui gagne les points.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Raison (ex. « reponse_acceptee », « vote_utile ») et nombre de points.
            $table->string('reason', 30);
            $table->smallInteger('points');

            // Contenu qui a rapporté les points (ex. une réponse).
            $table->string('subject_type', 20);
            $table->string('subject_id', 40);

            // Compte à l'origine des points (le votant, l'auteur de la question).
            $table->foreignId('source_user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->timestamp('created_at');

            // Les mêmes points ne peuvent pas être donnés deux fois pour la même raison.
            $table->unique(['user_id', 'reason', 'subject_type', 'subject_id', 'source_user_id'], 'reputation_events_unique')->nullsNotDistinct();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reputation_events');
    }
};
