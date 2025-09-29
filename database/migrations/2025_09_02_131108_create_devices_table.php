<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\text;

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
            $table->string('name')->nullable();
            $table->string('serial_number')->unique();
            $table->string('imei')->nullable();
            $table->text('fcm_token')->nullable();
            $table->enum('status', ["pending_registration","active","payment_due","locked","disabled"])->default("pending_registration");
            $table->timestamp('last_seen_at')->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
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
