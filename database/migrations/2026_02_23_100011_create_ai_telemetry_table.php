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
        Schema::create('ai_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('intent')->nullable();
            $table->string('target_block_key')->nullable();
            $table->string('target_step_key')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('reason')->nullable();
            $table->json('full_response_redacted')->nullable();
            $table->timestamps();
        });

        Schema::table('ai_telemetry', function (Blueprint $table) {
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_telemetry');
    }
};
