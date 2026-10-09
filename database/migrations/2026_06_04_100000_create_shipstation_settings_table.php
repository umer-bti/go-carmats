<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->required();
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $clientId = config('shipstation.client_id');
        $clientSecret = config('shipstation.client_secret');

        if ($clientId && $clientSecret) {
            DB::table('shipstation_settings')->insert([
                'name' => 'Shipstation Account (Main)',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_settings');
    }
};
