<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replacements', function (Blueprint $table) {
            $table->boolean('is_printed')->default(false)->after('description');
            $table->timestamp('printed_at')->nullable()->after('is_printed');
        });
    }

    public function down(): void
    {
        Schema::table('replacements', function (Blueprint $table) {
            $table->dropColumn(['is_printed', 'printed_at']);
        });
    }
};
