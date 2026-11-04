<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('level')->default('Beginner');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft'); // draft | published
            $table->boolean('sequential')->default(false);      // lessons must be completed in order
            $table->boolean('drip_enabled')->default(false);    // release lessons X days after enrollment
            $table->boolean('certificate_enabled')->default(true);
            $table->unsignedSmallInteger('access_days')->nullable(); // null = lifetime access
            $table->timestamps();
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('text'); // text | video | download | quiz | exam
            $table->longText('content')->nullable();   // markdown body / instructions
            $table->string('video_url')->nullable();
            $table->string('video_path')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->unsignedSmallInteger('drip_days')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_final')->default(false);
            $table->unsignedTinyInteger('passing_score')->default(70); // percent
            $table->unsignedSmallInteger('max_attempts')->nullable();  // null = unlimited
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('show_answers')->default(true);
            $table->boolean('require_all_lessons')->default(false);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('single'); // single | multiple | true_false | short
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->text('accepted_answers')->nullable(); // short answer, one per line
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('type')->default('percent'); // percent | fixed
            $table->decimal('value', 10, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->date('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 8);
            $table->string('gateway');
            $table->string('status')->default('pending'); // pending | paid | failed | refunded
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('enrolled_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->foreignId('last_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['enrollment_id', 'lesson_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('question_order');
            $table->json('answers')->nullable();
            $table->json('results')->nullable(); // per-question correctness
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->unsignedSmallInteger('points_total')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('passed')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['certificates', 'quiz_attempts', 'lesson_completions', 'enrollments', 'orders', 'coupons',
            'question_options', 'questions', 'quizzes', 'lessons', 'chapters', 'courses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
