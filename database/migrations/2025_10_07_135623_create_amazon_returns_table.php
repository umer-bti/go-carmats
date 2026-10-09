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
        Schema::create('amazon_returns', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->string('sku')->nullable();
            $table->string('item_name')->nullable();
            $table->string('material_type')->nullable();
            $table->dateTime('return_request_date')->nullable();
            $table->string('reason')->nullable();
            $table->string('return_type')->nullable();
            $table->string('status')->nullable();
            $table->string('tracking')->nullable();
            $table->boolean('received')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_returns');
    }
};
