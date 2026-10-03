<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Questions-réponses de la communauté.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('title', 150);
            // Texte en Markdown (avec blocs de code). L'API le stocke tel quel ; c'est l'affichage qui le met en forme.
            $table->text('body');

            // Réponse acceptée par l'auteur de la question. Une seule possible, puisque c'est une colonne.
            // La clé étrangère est ajoutée plus bas, une fois la table des réponses créée.
            $table->ulid('accepted_answer_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->text('body');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['question_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('questions', function (Blueprint $table) {
            // Si la réponse acceptée est effacée, la question redevient simplement « non résolue ».
            $table->foreign('accepted_answer_id')->references('id')->on('answers')->nullOnDelete();
        });

        // Technologies d'une question (« poser une question par technologie »).
        Schema::create('question_technology', function (Blueprint $table) {
            $table->foreignUlid('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();

            $table->primary(['question_id', 'technology_id']);
            $table->index('technology_id');
        });

        // Votes « Utile » : la clé primaire garantit un seul vote par compte et par réponse.
        Schema::create('answer_votes', function (Blueprint $table) {
            $table->foreignUlid('answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->primary(['answer_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['accepted_answer_id']);
        });

        Schema::dropIfExists('answer_votes');
        Schema::dropIfExists('question_technology');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');
    }
};
