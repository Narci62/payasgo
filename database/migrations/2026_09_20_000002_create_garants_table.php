<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garants', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->text('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('numero_identite')->nullable()->comment('NPI ou CIP');
            $table->string('photo_piece')->nullable()->comment('Chemin vers la photo de la piece d identite');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garants');
    }
};
