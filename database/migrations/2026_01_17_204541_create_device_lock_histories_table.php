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
        Schema::create('device_lock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->foreignId('financing_plan_id')->nullable()->constrained('financing_plans');

            // Action
            $table->enum('action', ['LOCK', 'UNLOCK', 'LOCK_ATTEMPT', 'UNLOCK_ATTEMPT']);
            $table->enum('trigger_reason', [
                'PAYMENT_OVERDUE',      // Retard de paiement
                'INACTIVITY_14_DAYS',   // Inactivité 14 jours
                'MANUAL_ADMIN',         // Action manuelle admin
                'PAYMENT_RECEIVED',     // Paiement reçu
                'ADMIN_OVERRIDE'        // Override administrateur
            ]);

            // Résultat
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
            $table->text('error_message')->nullable();

            // Contexte financier au moment de l'action
            $table->decimal('remaining_balance', 10, 2)->nullable();
            $table->integer('days_overdue')->nullable();
            $table->integer('days_inactive')->nullable();

            // Traçabilité
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users');
            $table->string('amapi_command_id')->nullable()->comment('ID de la commande AMAPI');
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->index(['device_id', 'action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_lock_histories');
    }
};
