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
        Schema::create('registration_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->onDelete("cascade");
            $table->foreignId("device_id")->nullable()->constrained()->onDelete("cascade");
            $table->string("token")->unique()->comment("Le jeton unique utilisé pour l'enregistrement.");
            $table->timestamp("used_at")->nullable()->comment("Date à laquelle le token a été utilisé.");
            $table->timestamp("expires_at")->comment("La date et l'heure d'expiration du jeton.");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_tokens');
    }
};
