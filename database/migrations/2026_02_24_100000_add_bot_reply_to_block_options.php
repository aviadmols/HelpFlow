<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Optional one-line bot reply shown when this option is clicked (before action runs).
     */
    public function up(): void
    {
        Schema::table('block_options', function (Blueprint $table) {
            $table->string('bot_reply', 512)->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('block_options', function (Blueprint $table) {
            $table->dropColumn('bot_reply');
        });
    }
};
