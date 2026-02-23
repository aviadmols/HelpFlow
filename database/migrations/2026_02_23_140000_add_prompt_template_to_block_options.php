<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Adds prompt_template for RUN_PROMPT action type (AI/ChatGPT prompt).
     */
    public function up(): void
    {
        Schema::table('block_options', function (Blueprint $table) {
            $table->text('prompt_template')->nullable()->after('confirm_step_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('block_options', function (Blueprint $table) {
            $table->dropColumn('prompt_template');
        });
    }
};
