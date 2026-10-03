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
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();

            // Type du jeton : « session » (humain, back-office) ou « agent_ia » (serveur MCP).
            $table->string('kind', 20);
            // Début du jeton (ex. sc_live_8f3k), affiché pour le reconnaître. Le jeton entier n'est jamais stocké.
            $table->string('display_prefix', 20);
            // Plafond de dépense mensuel en FCFA pour un jeton IA. Vide = pas de plafond.
            $table->integer('monthly_spending_cap_fcfa')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE personal_access_tokens ADD CONSTRAINT personal_access_tokens_cap_not_negative CHECK (monthly_spending_cap_fcfa >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
