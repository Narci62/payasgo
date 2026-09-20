<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            $table->enum('type', ['fixed_5000', 'fixed_10000', 'variable_5pct'])->nullable()->after('amount');
            $table->foreignId('installment_id')->nullable()->constrained()->onDelete('set null')->after('financing_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            $table->dropColumn(['type', 'installment_id']);
        });
    }
};
