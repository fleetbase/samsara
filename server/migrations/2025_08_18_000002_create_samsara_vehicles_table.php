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
            $table->foreignUuid('company_uuid')->nullable()->references('uuid')->on('companies')->onDelete('CASCADE');
            $table->foreignUuid('credential_uuid')->nullable()->references('uuid')->on('samsara_credentials')->onDelete('set null');
            $table->foreignUuid('vehicle_uuid')->nullable()->references('uuid')->on('vehicles')->onDelete('CASCADE');
            $table->string('samsara_vehicle_id')->index(); // Samsara vehicle ID
            $table->string('name')->nullable();
            $table->string('vin')->nullable();
            $table->string('serial')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('year')->nullable();
            $table->string('model')->nullable();
            $table->string('make')->nullable();
            $table->string('notes')->nullable();
            $table->string('regulation_mode')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->enum('sync_status', ['pending', 'syncing', 'active', 'failed', 'disabled'])->default('pending');
            $table->json('data')->nullable(); 
            $table->json('last_location')->nullable(); 
            $table->json('meta')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_uuid', 'samsara_vehicle_id']);
            $table->index(['company_uuid', 'sync_status']);
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

