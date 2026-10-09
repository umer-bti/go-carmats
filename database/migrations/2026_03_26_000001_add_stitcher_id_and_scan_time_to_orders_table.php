<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('stitcher_id')
                ->nullable()
                ->after('id')
                ->constrained('stitchers')
                ->nullOnDelete();
            $table->timestamp('scan_time')->nullable()->after('stitcher_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['stitcher_id']);
            $table->dropColumn(['stitcher_id', 'scan_time']);
        });
    }
};
