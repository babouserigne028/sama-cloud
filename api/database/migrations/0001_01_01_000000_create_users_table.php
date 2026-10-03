<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Rôle du compte : « client » ou « administrateur » (voir UserRole).
            $table->string('role', 20)->default('client');
            // Pays du développeur (code ISO à 2 lettres, ex. SN), utilisé par le classement par pays.
            $table->char('country_code', 2)->nullable();
            // Compte de démonstration du jury : quotas réduits et paiement simulé.
            $table->boolean('is_demo')->default(false);
            // Crédit en FCFA (paiement tardif crédité, remboursement en avoir).
            $table->unsignedInteger('credit_fcfa')->default(0);
            // Rempli quand un administrateur suspend un compte abusif.
            $table->timestamp('suspended_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // PostgreSQL n'a pas d'entier « non signé » : on interdit nous-mêmes un crédit négatif.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_credit_not_negative CHECK (credit_fcfa >= 0)');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
