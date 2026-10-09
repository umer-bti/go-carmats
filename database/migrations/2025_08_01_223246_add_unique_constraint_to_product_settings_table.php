<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_settings', function (Blueprint $table) {
            // Add unique constraint on name field
            $table->unique('name', 'product_settings_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_settings', function (Blueprint $table) {
            $table->dropUnique('product_settings_name_unique');
        });
    }
};
