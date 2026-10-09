<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evri_shipment_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('system_status')->nullable();
            $table->boolean('system_shipped')->default(false);
            $table->boolean('system_has_label')->default(false);
            $table->string('system_tracking_number')->nullable();
            $table->boolean('evri_label_exists')->nullable();
            $table->string('evri_status')->nullable();
            $table->text('evri_status_detail')->nullable();
            $table->string('verification_result')->default('pending');
            $table->timestamp('evri_verified_at')->nullable();
            $table->text('evri_error')->nullable();
            $table->timestamps();

            $table->index('verification_result');
            $table->index('evri_verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evri_shipment_verifications');
    }
};
