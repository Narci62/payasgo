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
        Schema::create('tauxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // valeur du taux en pourcentage
            $table->float('value', 10, 2)->comment('valeur du taux en pourcentage');
            // nombre de mois pour ce taux
            $table->integer('months')->comment('nombre de mois pour ce taux');
            // calcul en nombre de jours pour ce taux
            $table->integer('days')->comment('calcul en nombre de jours pour ce taux');
            // montant pour ce taux
            $table->float('taux_amount', 10, 2)->comment('montant pour ce taux');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tauxes');
    }
};
