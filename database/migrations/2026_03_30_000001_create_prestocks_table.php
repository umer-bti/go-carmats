<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_setting_id')->constrained('product_settings')->cascadeOnDelete();
            $table->unsignedInteger('stock')->default(0);
            $table->string('material', 32)->default('Carpet');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestocks');
    }
};
