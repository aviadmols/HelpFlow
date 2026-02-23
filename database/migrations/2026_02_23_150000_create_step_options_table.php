<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Step-level suggestions (options) for chat UI; each can trigger API_CALL, NEXT_STEP, etc.
     */
    public function up(): void
    {
        Schema::create('step_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('steps')->cascadeOnDelete();
            $table->string('label');
            $table->string('bot_reply', 512)->nullable();
            $table->string('action_type');
            $table->foreignId('endpoint_id')->nullable()->constrained('endpoints')->nullOnDelete();
            $table->json('payload_mapper')->nullable();
            $table->text('success_template')->nullable();
            $table->text('failure_template')->nullable();
            $table->foreignId('next_step_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->foreignId('next_step_on_failure_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->foreignId('confirm_step_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->text('prompt_template')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_options');
    }
};
