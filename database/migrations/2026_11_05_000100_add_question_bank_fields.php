<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // null = every question in the bank goes into each attempt
            $table->unsignedSmallInteger('questions_per_attempt')->nullable()->after('time_limit_minutes');
        });

        Schema::table('questions', function (Blueprint $table) {
            // e.g. "Module 4". Attempts drawn from a bank take a fair share from each topic.
            $table->string('topic', 120)->nullable()->after('quiz_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn('topic'));
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn('questions_per_attempt'));
    }
};
