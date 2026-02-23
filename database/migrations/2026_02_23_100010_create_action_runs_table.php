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
        Schema::create('action_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('block_option_id')->nullable()->constrained('block_options')->nullOnDelete();
            $table->foreignId('endpoint_id')->nullable()->constrained('endpoints')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->text('request_redacted')->nullable();
            $table->text('response_redacted')->nullable();
            $table->unsignedInteger('http_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::table('action_runs', function (Blueprint $table) {
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_runs');
    }
};
