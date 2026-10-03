<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services d'un projet (web ou worker), décrits dans datacloud.yaml.
     */
    public function up(): void
    {
        Schema::create('project_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();

            $table->string('name', 30);
            $table->string('type', 10);
            // Vide si le projet fournit son propre Dockerfile.
            $table->string('framework', 20)->nullable();
            $table->string('size', 10);

            // Adresse publique (services web uniquement), ex. mon-blog.exemple.sn.
            $table->string('hostname')->nullable()->unique();

            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_services');
    }
};
