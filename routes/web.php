<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProctorPortalController;
use App\Http\Controllers\ProctorRequestController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\Studio\CouponController;
use App\Http\Controllers\Studio\CourseController;
use App\Http\Controllers\Studio\CurriculumController;
use App\Http\Controllers\Studio\ExamAccessController;
use App\Http\Controllers\Studio\ProctoringController;
use App\Http\Controllers\Studio\QuestionController;
use App\Http\Controllers\Studio\QuestionImportController;
use App\Http\Controllers\Studio\ReportController;
use App\Http\Controllers\Studio\StudioController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/courses/{course}', [CatalogController::class, 'show'])->name('courses.show');
Route::get('/verify', [CertificateController::class, 'verify'])->name('certificates.verify');

// Proctor's private page (link from their email; no account)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/proctor/{token}', [ProctorPortalController::class, 'show'])->name('proctor.show');
    Route::post('/proctor/{token}', [ProctorPortalController::class, 'confirm'])->name('proctor.confirm');
});
Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
Route::match(['get', 'post'], '/webhooks/{gateway}', [CheckoutController::class, 'webhook'])->name('checkout.webhook');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Students
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [StudentController::class, 'orders'])->name('orders');

    Route::get('/checkout/{course}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{course}', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::get('/checkout/order/{order}/sandbox', [CheckoutController::class, 'sandbox'])->name('checkout.sandbox');
    Route::post('/checkout/order/{order}/sandbox', [CheckoutController::class, 'sandboxDecide']);
    Route::get('/checkout/order/{order}/return', [CheckoutController::class, 'return'])->name('checkout.return');

    Route::get('/learn/{course}', [LearnController::class, 'resume'])->name('learn.resume');
    Route::get('/learn/{course}/lessons/{lesson}', [LearnController::class, 'show'])->name('learn.lesson');
    Route::post('/learn/{course}/lessons/{lesson}/complete', [LearnController::class, 'complete'])->name('learn.complete');
    Route::get('/learn/{course}/lessons/{lesson}/download', [LearnController::class, 'download'])->name('learn.download');

    Route::post('/learn/{course}/lessons/{lesson}/attempts', [QuizController::class, 'start'])->name('quiz.start');
    Route::get('/learn/{course}/lessons/{lesson}/proctor', [ProctorRequestController::class, 'create'])->name('proctor-requests.create');
    Route::post('/learn/{course}/lessons/{lesson}/proctor', [ProctorRequestController::class, 'store'])->middleware('throttle:10,1')->name('proctor-requests.store');
    Route::post('/learn/{course}/lessons/{lesson}/proctor/{proctorRequest}/cancel', [ProctorRequestController::class, 'cancel'])->name('proctor-requests.cancel');
    Route::get('/attempts/{attempt}', [QuizController::class, 'show'])->name('attempts.show');
    Route::post('/attempts/{attempt}', [QuizController::class, 'submit'])->name('attempts.submit');
    Route::post('/attempts/{attempt}/autosave', [QuizController::class, 'autosave'])->name('attempts.autosave');
});

// Instructor studio
Route::middleware(['auth', 'role:admin,instructor'])->prefix('studio')->name('studio.')->group(function () {
    Route::get('/', [StudioController::class, 'dashboard'])->name('dashboard');
    Route::get('/sales', [StudioController::class, 'sales'])->name('sales');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/new', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}/settings', [CourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
    Route::post('/courses/{course}/publish', [CourseController::class, 'togglePublish'])->name('courses.publish');

    Route::get('/courses/{course}/curriculum', [CurriculumController::class, 'show'])->name('curriculum');
    Route::post('/courses/{course}/chapters', [CurriculumController::class, 'storeChapter'])->name('chapters.store');
    Route::put('/chapters/{chapter}', [CurriculumController::class, 'updateChapter'])->name('chapters.update');
    Route::delete('/chapters/{chapter}', [CurriculumController::class, 'destroyChapter'])->name('chapters.destroy');
    Route::post('/chapters/{chapter}/lessons', [CurriculumController::class, 'storeLesson'])->name('lessons.store');
    Route::get('/lessons/{lesson}/edit', [CurriculumController::class, 'editLesson'])->name('lessons.edit');
    Route::put('/lessons/{lesson}', [CurriculumController::class, 'updateLesson'])->name('lessons.update');
    Route::delete('/lessons/{lesson}', [CurriculumController::class, 'destroyLesson'])->name('lessons.destroy');
    Route::post('/courses/{course}/reorder', [CurriculumController::class, 'reorder'])->name('curriculum.reorder');

    Route::post('/lessons/{lesson}/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::put('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::post('/lessons/{lesson}/questions/reorder', [QuestionController::class, 'reorder'])->name('questions.reorder');
    Route::post('/lessons/{lesson}/questions/import', [QuestionImportController::class, 'preview'])->name('questions.import.preview');
    Route::post('/lessons/{lesson}/questions/import/confirm', [QuestionImportController::class, 'store'])->name('questions.import.store');
    Route::get('/lessons/{lesson}/questions/template', [QuestionImportController::class, 'template'])->name('questions.template');

    Route::get('/courses/{course}/students', [ReportController::class, 'students'])->name('students');
    Route::post('/courses/{course}/students', [ReportController::class, 'enrollStudent'])->name('students.enroll');
    Route::get('/courses/{course}/students/{enrollment}', [ReportController::class, 'student'])->name('students.show');
    Route::post('/courses/{course}/students/{enrollment}/exam-codes', [ExamAccessController::class, 'store'])->name('exam-codes.store');
    Route::post('/exam-codes/{code}/revoke', [ExamAccessController::class, 'revoke'])->name('exam-codes.revoke');
    Route::get('/proctoring', [ProctoringController::class, 'index'])->name('proctoring');
    Route::post('/proctoring/{proctorRequest}/approve', [ProctoringController::class, 'approve'])->name('proctoring.approve');
    Route::post('/proctoring/{proctorRequest}/decline', [ProctoringController::class, 'decline'])->name('proctoring.decline');
    Route::post('/proctoring/{proctorRequest}/resend', [ProctoringController::class, 'resend'])->name('proctoring.resend');
    Route::get('/courses/{course}/quiz-reports', [ReportController::class, 'quizzes'])->name('reports.quizzes');

    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::post('/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
});

// Admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::get('/courses', [AdminController::class, 'courses'])->name('courses');
    Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
});
