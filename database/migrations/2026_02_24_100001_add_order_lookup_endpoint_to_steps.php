<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Optional endpoint to call after collecting email/order (e.g. fetch order details).
     */
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->foreignId('order_lookup_endpoint_id')->nullable()->after('fallback_block_id')->constrained('endpoints')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropForeign(['order_lookup_endpoint_id']);
        });
    }
};
