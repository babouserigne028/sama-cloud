<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil public d'un développeur : sa vitrine dans la communauté.
     * Les informations sensibles (e-mail, mot de passe, rôle) restent dans « users ».
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            // Un profil par compte : l'identifiant du compte sert de clé.
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();

            // Pseudo unique, utilisé dans l'adresse du profil (ex. /profils/awa-diop).
            $table->string('username', 24)->unique();
            // Phrase de présentation courte (ex. « Développeuse Laravel à Dakar »).
            $table->string('headline', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('availability', 20);

            $table->string('github_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('linkedin_url')->nullable();

            $table->timestamps();

            $table->index('availability');
        });

        // Minuscules, chiffres et tirets ; 3 à 24 caractères ; ne commence ni ne finit par un tiret.
        DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_username_format CHECK (username ~ '^[a-z0-9][a-z0-9-]{1,22}[a-z0-9]$')");

        // Compétences d'un profil.
        Schema::create('profile_technology', function (Blueprint $table) {
            $table->foreignId('profile_user_id')->constrained('profiles', 'user_id')->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();

            $table->primary(['profile_user_id', 'technology_id']);
            $table->index('technology_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_technology');
        Schema::dropIfExists('profiles');
    }
};
