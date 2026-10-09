<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
            $table->string('order_item_id')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('no_of_clips')->nullable();
            $table->string('material_type')->nullable();
            $table->string('product_code')->nullable();
            $table->string('quantity')->nullable();
            $table->string('product')->nullable();
            $table->string('edging')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('description')->nullable();
            $table->string('date')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};