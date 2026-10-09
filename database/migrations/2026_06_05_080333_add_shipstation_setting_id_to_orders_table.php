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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipstation_setting_id')
              ->nullable()
              ->after('stitcher_id')
              ->constrained('shipstation_settings')
              ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['shipstation_setting_id']);
                $table->dropColumn('shipstation_setting_id');
        });
    }
};
