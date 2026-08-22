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
        Schema::create('amapi_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');

            // Identifiants AMAPI
            $table->string('amapi_device_id')->unique()->comment('ID unique fourni par AMAPI');
            $table->string('amapi_enterprise_id')->comment('ID de votre entreprise AMAPI');
            $table->string('amapi_policy_id')->nullable()->comment('ID de la politique appliquée');

            // État de l'appareil côté AMAPI
            $table->enum('amapi_state', [
                'ACTIVE',           // Appareil actif et fonctionnel
                'DISABLED',         // Appareil désactivé (verrouillé)
                'DELETED',          // Appareil supprimé de AMAPI
                'PROVISIONING',      // En cours de provisioning
            ])->default('PROVISIONING');

            // Informations de provisioning
            $table->string('enrollment_token')->nullable()->comment('Token pour QR code provisioning');
            $table->timestamp('enrolled_at')->nullable()->comment('Date d\'enrollment réussie');
            $table->text('qr_code_data')->nullable()->comment('Données JSON du QR code');

            // Suivi des commandes
            $table->timestamp('last_command_sent_at')->nullable();
            $table->string('last_command_type')->nullable(); // 'LOCK', 'UNLOCK', 'WIPE', etc.
            $table->enum('last_command_status', ['PENDING', 'SUCCESS', 'FAILED'])->nullable();
            $table->text('last_command_error')->nullable();

            // Synchronisation
            $table->timestamp('last_amapi_sync_at')->nullable();
            $table->json('amapi_metadata')->nullable()->comment('Métadonnées additionnelles AMAPI');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['amapi_device_id', 'amapi_state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amapi_devices');
    }
};
