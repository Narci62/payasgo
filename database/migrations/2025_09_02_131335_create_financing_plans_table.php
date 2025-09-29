<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId("device_id")->nullable()->constrained()->onDelete("cascade");
            $table->foreignId("registration_token_id")->constrained("registration_tokens")->onDelete("cascade");
            $table->decimal("total_price", 10, 2)->comment("Le prix total de vente du téléphone pour ce client.");
            $table->decimal("down_payment", 10, 2)->default(0)->comment("L'acompte initial payé par le client lors de l'achat.");
            $table->decimal("remaining_balance", 10, 2)->default(0)->comment("Le solde restant dû. Ce montant est décrémenté après chaque paiement.");
            $table->decimal("installment_amount", 10, 2)->default(0)->comment("Le montant fixe de chaque versement périodique");
            $table->enum("status", ["active", "paid_in_full", "defaulted"])->default("defaulted");
            $table->date("next_payment_due_date")->nullable()->comment("La date à laquelle le prochain paiement est dû.");
            $table->date("grace_period_ends_at")->nullable()->comment("La date à laquelle la période de grâce se termine.");
            $table->string("next_offline_unlock_code")->nullable()->comment("Le code de déverrouillage hors ligne pour le prochain paiement.");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financing_plans');
    }
};
