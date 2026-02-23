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
        Schema::create('block_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->string('label');
            $table->string('action_type'); // API_CALL | NEXT_STEP | CONFIRM | HUMAN_HANDOFF | OPEN_URL | NO_OP
            $table->foreignId('endpoint_id')->nullable()->constrained('endpoints')->nullOnDelete();
            $table->json('payload_mapper')->nullable();
            $table->text('success_template')->nullable();
            $table->text('failure_template')->nullable();
            $table->foreignId('next_step_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->foreignId('next_step_on_failure_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->foreignId('confirm_step_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->json('retry_policy')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_options');
    }
};
