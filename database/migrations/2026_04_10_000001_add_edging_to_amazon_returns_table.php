<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_returns', function (Blueprint $table) {
            $table->string('edging')->nullable()->after('material_type');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_returns', function (Blueprint $table) {
            $table->dropColumn('edging');
        });
    }
};
