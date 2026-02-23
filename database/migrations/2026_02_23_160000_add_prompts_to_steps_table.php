<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Step-level AI prompts (override flow when set).
     */
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->text('router_prompt')->nullable()->after('bot_message_template');
            $table->text('system_prompt')->nullable()->after('router_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn(['router_prompt', 'system_prompt']);
        });
    }
};
