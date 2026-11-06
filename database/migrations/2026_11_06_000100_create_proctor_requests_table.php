<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctor_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();

            // Chosen by the student
            $table->string('proctor_name', 120);
            $table->string('proctor_email', 190);
            $table->string('proctor_phone', 40)->nullable();
            $table->string('proctor_relationship', 120)->nullable(); // e.g. "Colleague at my host agency"
            $table->timestamp('preferred_at')->nullable();             // stored in UTC
            $table->string('timezone', 64)->nullable();                 // the student's, for display
            $table->text('student_notes')->nullable();

            // pending | approved | declined | cancelled
            $table->string('status', 16)->default('pending');
            $table->text('decision_note')->nullable();                  // shown to the student (and proctor, if approved)
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('exam_access_code_id')->nullable()->constrained()->nullOnDelete();

            // The proctor's private page: only a hash is stored; the link is in their email.
            $table->string('proctor_token_hash', 64)->nullable()->unique();
            $table->timestamp('proctor_confirmed_at')->nullable();
            $table->boolean('proctor_flagged')->default(false);
            $table->text('proctor_report')->nullable();

            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctor_requests');
    }
};
