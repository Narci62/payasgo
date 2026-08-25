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
        Schema::table('financing_plans', function (Blueprint $table) {
            $table->string('amapi_sync_status')->default('pending')->after('status');
            $table->text('amapi_sync_error')->nullable()->after('amapi_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financing_plans', function (Blueprint $table) {
            $table->dropColumn(['amapi_sync_status', 'amapi_sync_error']);
        });
    }
};
