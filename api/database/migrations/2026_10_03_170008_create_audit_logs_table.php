<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'audit : qui a fait quoi, et si c'était un humain ou l'IA.
     * La table est en « ajout seul » : PostgreSQL refuse toute modification ou suppression.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Compte concerné. Vide pour une action de la plateforme elle-même.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('actor', 10);
            // Jeton utilisé. Pas de clé étrangère : la ligne survit à la révocation du jeton.
            $table->unsignedBigInteger('token_id')->nullable();

            // Code de l'action, ex. « projet.cree », « deploiement.lance ».
            $table->string('action', 100);

            // Projet et objet concernés. Pas de clé étrangère : la ligne survit à leur suppression.
            $table->ulid('project_id')->nullable();
            $table->string('subject_type', 50)->nullable();
            $table->string('subject_id', 40)->nullable();

            // Précisions utiles. Jamais de secret ni de valeur de variable.
            $table->jsonb('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });

        // Fonction et déclencheurs qui refusent toute modification du journal.
        // « OR REPLACE » : migrate:fresh efface les tables mais pas les fonctions.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_block_changes() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs est en ajout seul : % interdit', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_block_changes();

            CREATE TRIGGER audit_logs_no_truncate
                BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_block_changes();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_block_changes()');
    }
};
