<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_plan_id')->constrained()->onDelete('cascade');
            $table->date('due_date')->comment("Date d'expiration de l'échéance");
            $table->decimal('amount', 10, 2)->comment("Montant initial de l'échéance");
            $table->decimal('remaining_amount', 10, 2)->comment('Reste à payer sur le principal');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
