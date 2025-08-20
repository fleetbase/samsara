<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('samsara_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('public_id')->unique();
            $table->string('company_uuid')->index();
            $table->string('vehicle_uuid')->nullable()->index(); // FleetOps vehicle UUID
            $table->string('samsara_vehicle_id')->index(); // Samsara vehicle ID
            $table->string('samsara_vehicle_name')->nullable();
            $table->string('samsara_vehicle_vin')->nullable();
            $table->string('samsara_vehicle_serial')->nullable();
            $table->json('samsara_vehicle_data')->nullable(); // Full Samsara vehicle data
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('sync_status', ['pending', 'syncing', 'active', 'failed', 'disabled'])->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_uuid', 'samsara_vehicle_id']);
            $table->index(['company_uuid', 'sync_status']);
            $table->index(['vehicle_uuid']);
            $table->index(['last_sync_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('samsara_vehicles');
    }
};

