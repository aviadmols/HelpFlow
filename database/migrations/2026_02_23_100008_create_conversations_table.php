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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('steps')->nullOnDelete();
            $table->text('context')->nullable(); // encrypted json
            $table->string('last_presented_block_key')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['tenant_id', 'customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
