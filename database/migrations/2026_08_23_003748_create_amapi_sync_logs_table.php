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
        Schema::create('amapi_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->enum('action', ['LOCK', 'UNLOCK', 'DELETE']);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amapi_sync_logs');
    }
};
