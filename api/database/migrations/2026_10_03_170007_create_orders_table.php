<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commandes : un devis validé, à payer avant la création des ressources.
     * Les montants sont en FCFA entiers (le franc CFA n'a pas de centimes).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // La trace de la commande est conservée même si le projet est effacé.
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 30);
            // Commande créée par un humain ou par l'IA.
            $table->string('actor', 10);

            $table->unsignedInteger('amount_fcfa');
            // Détail du devis, ligne par ligne, tel que calculé par l'API.
            $table->jsonb('quote');

            // Lien de paiement et ses limites dans le temps (15 minutes).
            $table->text('payment_url')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('capacity_reserved_until')->nullable();

            // Fournisseur de paiement et sa référence, unique pour ne jamais créditer deux fois.
            $table->string('provider', 30)->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();

            // Période de 30 jours couverte par le paiement.
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'payment_expires_at']);
        });

        // PostgreSQL n'a pas d'entier « non signé » : on interdit nous-mêmes les montants négatifs.
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amount_not_negative CHECK (amount_fcfa >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
