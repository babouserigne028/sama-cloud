<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technologies connues de la plateforme (Laravel, Angular, PostgreSQL…).
     * Elles servent aux compétences des profils, aux questions et aux appels à collaboration.
     */
    public function up(): void
    {
        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            // Identifiant lisible utilisé dans les adresses et les filtres (ex. « laravel »).
            $table->string('slug', 40)->unique();
            // Nom affiché (ex. « Laravel »).
            $table->string('name', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technologies');
    }
};
