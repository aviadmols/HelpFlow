<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Step goal: when set, the AI keeps the conversation in this step until the goal is achieved.
     * next_step_id_when_goal_achieved: optional transition when goal is achieved.
     */
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->text('goal')->nullable()->after('context_variables');
            $table->unsignedBigInteger('next_step_id_when_goal_achieved')->nullable()->after('goal');
        });

        Schema::table('steps', function (Blueprint $table) {
            $table->foreign('next_step_id_when_goal_achieved')->references('id')->on('steps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropForeign(['next_step_id_when_goal_achieved']);
        });
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn(['goal', 'next_step_id_when_goal_achieved']);
        });
    }
};
