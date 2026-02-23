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
        Schema::create('endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('key')->index();
            $table->string('name');
            $table->string('method', 10)->default('POST');
            $table->text('url');
            $table->text('headers')->nullable(); // encrypted json
            $table->string('auth_type')->nullable();
            $table->text('auth_config')->nullable(); // encrypted
            $table->unsignedInteger('timeout_sec')->default(30);
            $table->unsignedInteger('retries')->default(0);
            $table->json('request_mapper')->nullable();
            $table->json('response_mapper')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endpoints');
    }
};
