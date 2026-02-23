<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Defines which context/session variable keys this step can write (e.g. email, order_number).
     * The AI router is instructed to return these in the "variables" object; they are merged into conversation context.
     */
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->json('context_variables')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn('context_variables');
        });
    }
};
