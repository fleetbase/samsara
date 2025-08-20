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
        Schema::create('samsara_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('public_id')->unique();
            $table->string('company_uuid')->index();
            $table->string('name');
            $table->text('api_token'); // Encrypted
            $table->string('api_base_url')->default('https://api.samsara.com');
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable(); // Encrypted
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false);
            $table->integer('sync_interval')->default(5); // Minutes
            $table->timestamp('last_sync_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('samsara_credentials');
    }
};

