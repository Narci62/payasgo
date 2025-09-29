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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("financing_plan_id")->constrained()->onDelete("cascade");
            $table->decimal("amount", 10, 2)->comment("Le montant du paiement.");
            $table->string('currency')->default("XOF");
            $table->enum("method", ["mobile_money", "manual", "cash"])->default("mobile_money");
            $table->string("transaction_id")->comment("L'ID de la transaction pour le suivi.");
            $table->timestamp("paid_at")->nullable()->comment("La date et l'heure auxquelles le paiement a été effectué.");
            $table->enum("status", ["pending", "completed", "failed"])->default("pending");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
