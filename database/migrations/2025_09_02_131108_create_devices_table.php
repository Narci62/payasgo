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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('public_id')->unique();
            $table->string('android_version')->nullable();
            $table->string('device_name');
            $table->string('device_id')->unique();
            $table->string('device_model')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('imei')->nullable();
            $table->text('fcm_token')->nullable();
            $table->enum('status', ['pending_registration', 'active', 'payment_due', 'locked', 'disabled'])->default('pending_registration');
            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
