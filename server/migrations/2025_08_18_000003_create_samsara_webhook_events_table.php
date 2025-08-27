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
        Schema::create('samsara_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('public_id')->unique();
            $table->foreignUuid('company_uuid')->nullable()->references('uuid')->on('companies')->onDelete('CASCADE');
            $table->foreignUuid('credential_uuid')->nullable()->references('uuid')->on('samsara_credentials')->onDelete('CASCADE');
            $table->foreignUuid('samsara_vehicle_uuid')->nullable()->references('uuid')->on('samsara_vehicles')->onDelete('set null');
            $table->string('event_id')->nullable()->index(); // Samsara event ID
            $table->string('event_type')->index(); // alert, location, etc.
            $table->json('event_data'); // Full webhook payload
            $table->timestamp('processed_at')->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_uuid', 'processing_status']);
            $table->index(['event_type', 'processing_status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('samsara_webhook_events');
    }
};

