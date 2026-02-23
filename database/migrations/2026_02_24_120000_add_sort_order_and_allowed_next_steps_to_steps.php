<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds sort_order for display order and allowed_next_step_ids to restrict transitions from this step.
     */
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('flow_id');
            $table->json('allowed_next_step_ids')->nullable()->after('transition_rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'allowed_next_step_ids']);
        });
    }
};
