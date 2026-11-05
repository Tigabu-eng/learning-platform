<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // open | password (one shared password) | code (one-time code per student, given to their proctor)
            $table->string('access_mode', 16)->default('open')->after('require_all_lessons');
            $table->text('access_password')->nullable()->after('access_mode'); // encrypted, so staff can read it back
        });

        Schema::create('exam_access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->text('code'); // encrypted
            $table->string('proctor_name', 120)->nullable();
            $table->string('proctor_email', 190)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('quiz_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['quiz_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_access_codes');
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn(['access_mode', 'access_password']));
    }
};
