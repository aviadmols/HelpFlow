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
        Schema::create('steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->string('key')->index();
            $table->text('bot_message_template')->nullable();
            $table->json('allowed_block_ids')->nullable();
            $table->json('transition_rules')->nullable();
            $table->foreignId('fallback_block_id')->nullable()->constrained('blocks')->nullOnDelete();
            $table->string('ai_model_override')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('steps');
    }
};
